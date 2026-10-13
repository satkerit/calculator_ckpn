<?php

namespace Tests\Feature;

use App\Models\AssessmentPeriod;
use App\Models\PdNetflowBucketSnapshot;
use App\Models\PdNetflowCompoundFlow;
use App\Models\PdNetflowTransition;
use App\Models\User;
use App\Services\PdNetflow\PdNetflowCalculator;
use App\Services\PdNetflow\PdNetflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PdNetflowTableRulesTest extends TestCase
{
    use RefreshDatabase;

    private function createPeriod(): AssessmentPeriod
    {
        $user = User::factory()->create();

        return AssessmentPeriod::create([
            'period_code' => '2026-08',
            'period_name' => 'Agustus 2026',
            'fiscal_month' => 8,
            'fiscal_year' => 2026,
            'assessment_date' => '2026-08-31',
            'status' => AssessmentPeriod::STATUS_DRAFT,
            'created_by' => $user->id,
        ]);
    }

    public function testTableOneIncludesAllFourteenBucketsEvenWhenEmpty(): void
    {
        $period = $this->createPeriod();
        $service = new PdNetflowService();

        // Simpan hanya bucket 1 dan 14 (bucket 2 s/d 13 kosong di database)
        PdNetflowBucketSnapshot::create([
            'assessment_period_id' => $period->id,
            'group_key' => '010|MURABAHAH|MODAL_KERJA',
            'bucket_no' => 1,
            'periode_snapshot' => '2026-08-31',
            'outstanding_balance' => 1000000000,
            'account_count' => 10,
        ]);
        PdNetflowBucketSnapshot::create([
            'assessment_period_id' => $period->id,
            'group_key' => '010|MURABAHAH|MODAL_KERJA',
            'bucket_no' => 14,
            'periode_snapshot' => '2026-08-31',
            'outstanding_balance' => 50000000,
            'account_count' => 2,
        ]);

        $detail = $service->loadGroupDetail($period->id, '010|MURABAHAH|MODAL_KERJA');

        // Pastikan tabel 1 memuat 14 bucket utuh
        $this->assertCount(14, $detail['buckets']);
        for ($i = 1; $i <= 14; $i++) {
            $this->assertArrayHasKey($i, $detail['buckets']);
            $this->assertEquals($i, $detail['buckets'][$i]['bucket_no']);
        }

        // Bucket 2 bernilai kosong tapi tetap ada
        $this->assertEmpty($detail['buckets'][2]['periods']);
    }

    public function testTableThreeCompoundFlowEndsSameAsTableOneAndCalculatesPdRateAverage(): void
    {
        $period = $this->createPeriod();
        $service = new PdNetflowService();

        // Buat data transition dan compound flow yang berakhir di 2026-08-31
        PdNetflowTransition::create([
            'assessment_period_id' => $period->id,
            'group_key' => '010|MURABAHAH|MODAL_KERJA',
            'from_bucket' => 1,
            'to_bucket' => 2,
            'periode' => '2026-08-31',
            'transition_rate' => 0.05,
            'flow_count' => 0,
        ]);

        PdNetflowCompoundFlow::create([
            'assessment_period_id' => $period->id,
            'group_key' => '010|MURABAHAH|MODAL_KERJA',
            'source_bucket' => 1,
            'periode' => '2026-07-31',
            'compound_pd' => 0.02,
        ]);

        PdNetflowCompoundFlow::create([
            'assessment_period_id' => $period->id,
            'group_key' => '010|MURABAHAH|MODAL_KERJA',
            'source_bucket' => 1,
            'periode' => '2026-08-31',
            'compound_pd' => 0.04,
        ]);

        $compounds = $service->loadCompoundFlows($period->id, '010|MURABAHAH|MODAL_KERJA');

        // Source bucket 1 s/d 13 ada
        $this->assertCount(13, $compounds);
        $this->assertArrayHasKey(1, $compounds);

        // Periode terakhir compound berakhir di 2026-08-31
        $periods = array_keys($compounds[1]['periods']);
        $this->assertStringStartsWith('2026-08-31', end($periods));

        // PD Rate = MIN(100%, AVERAGE(0.02, 0.04)) = 0.03
        $this->assertEqualsWithDelta(0.03, $compounds[1]['pd_rate'], 0.000001);

        // Verifikasi loadGroupDetail memisahkan transition_periods dan compound_periods
        $detail = $service->loadGroupDetail($period->id, '010|MURABAHAH|MODAL_KERJA');
        $this->assertArrayHasKey('transition_periods', $detail);
        $this->assertArrayHasKey('compound_periods', $detail);
        $this->assertContains('2026-08-31 00:00:00', $detail['compound_periods']);
    }

    public function testProjectionUsesRollingAverageOfPreviousSixMonths(): void
    {
        $period = $this->createPeriod();
        $calculator = new PdNetflowCalculator();
        $groupKey = '010|MURABAHAH|MODAL_KERJA';

        // Data historis Tabel 1: 2026-02 s/d 2026-08.
        // assessmentDate = bulan berikutnya (2026-09) agar Tabel 1 berakhir di Agustus 2026.
        // rate(P) = outstanding_bucket2(P) / outstanding_bucket1(P-1)
        $bucketOutstanding = [];
        $periods = [];
        $b1 = 1000.0; // bucket 1 tetap 1000 tiap periode
        $b2 = 100.0;  // bucket 2 tetap 100 tiap periode

        foreach (['202602', '202603', '202604', '202605', '202606', '202607', '202608'] as $ym) {
            $periods[] = $ym;
            $bucketOutstanding["{$ym}_1"] = $b1;
            $bucketOutstanding["{$ym}_2"] = $b2;
        }

        $assessmentDate = Carbon::create(2026, 9, 30);
        $calculator->calculateTransitions($period->id, $groupKey, $bucketOutstanding, $periods, 6, $assessmentDate);

        $rows = PdNetflowTransition::where('assessment_period_id', $period->id)
            ->where('group_key', $groupKey)
            ->where('from_bucket', 1)
            ->orderBy('periode')
            ->get();

        // Historis 2026-02..2026-08 (7 titik Tabel 1) -> 6 pasangan transisi
        // + proyeksi 12 bulan (2026-09..2027-08) = 18 baris
        $this->assertCount(18, $rows);

        $histRate = 100.0 / 1000.0; // 0.1
        foreach ($rows as $row) {
            $this->assertEqualsWithDelta($histRate, (float) $row->transition_rate, 0.000001);
        }

        // Periode proyeksi pertama = 2026-09-30 s/d terakhir 2027-08-31
        $projectionRows = $rows->filter(fn ($r) => $r->periode >= '2026-09-01')->values();
        $this->assertCount(12, $projectionRows);
        $this->assertStringStartsWith('2026-09-30', $projectionRows->first()->periode);
        $this->assertStringStartsWith('2027-08-31', $projectionRows->last()->periode);
    }
}
