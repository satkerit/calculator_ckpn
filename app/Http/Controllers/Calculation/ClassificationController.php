<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Snapshot;
use App\Services\Bucket\BucketAssignmentService;
use App\Services\Ead\EadStrategyFactory;
use App\Services\ProductAkadMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use function count;

/**
 * Modul 2: Klasifikasi & Segmentasi.
 *
 * Menetapkan INDIVIDUAL vs COLLECTIVE per loan, lalu mengelompokkan
 * kolektif berdasarkan dimensi segmentasi yang dipilih user (combo).
 */
class ClassificationController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period         = $this->resolvePeriod($request);
        $config         = $this->configFor($period->id);
        $periods        = $this->periods();
        $counts         = $this->countsFor($period->id);
        $classifications = $this->preview($period, $config);

        return view('calculation.modules.classification', compact(
            'period',
            'periods',
            'config',
            'counts',
            'classifications',
        ));
    }

    public function run(Request $request): RedirectResponse
    {
        $period = $this->resolvePeriod($request);
        $config = $this->configFor($period->id);
        $dims   = $this->segmentDimensions($request, $period->id);

        $topN       = (int) $config['individual_top_n'];
        $nplRatings = $config['npl_ratings'];

        $periodeYm = $period->assessment_date?->format('Ym') ?? '999912';

        // 0) Assign bucket_no ke snapshot untuk persiapan PD Net Flow
        $bucketService = app(BucketAssignmentService::class);
        $bucketsAssigned = $bucketService->assignBucketsForPeriodFast($periodeYm);

        $snapshots = Snapshot::where('periode_snapshot', $periodeYm)
            ->where('active_flag', true)  // Filter: hanya pembiayaan aktif
            ->where(function ($q) {
                $q->whereNull('write_off_flag')->orWhere('write_off_flag', false);
            })
            ->orderBy('loan_number')
            ->get();

        if ($snapshots->isEmpty()) {
            return $this->backTo($request, 'calculation.module.classification', [], "Snapshot {$periodeYm} tidak ditemukan.");
        }

        // 1) Hitung EAD + tandai kandidat NPL per loan (dinamis, tanpa nominal threshold).
        $rows = [];
        foreach ($snapshots as $s) {
            $akadCode  = ProductAkadMappingService::resolveAkadCode($s->product_code ?? '');
            $usageCode = $s->usage_type ?? $s->financing_contract_code ?? 'OTHER';
            $ead       = EadStrategyFactory::createByAkad($akadCode)
                ->calculate($s->toArray(), $period->assessment_date);

            $rating = (int) ($s->quality_rating ?? 0);

            // NPL ditentukan dari KUALITAS pembiayaan (kolektibilitas), bukan DPD.
            // Kualitas 3,4,5 = NPL. Write-off selalu dianggap NPL.
            $isNpl = ! empty($s->write_off_flag)
                || in_array($rating, $nplRatings, true);

            $rows[] = [
                'snapshot'  => $s,
                'akad_code' => $akadCode,
                'usage_code'=> $usageCode,
                'ead'       => $ead,
                'rating'    => $rating,
                'is_npl'    => $isNpl,
                // Ranking outstanding: nilai tercatat (pokok + tunggakan pokok).
                'outstanding' => (float) $s->outstanding_balance + (float) $s->principal_arrears,
                '_individual' => false,
                '_reason'     => null,
            ];
        }

        // 2) Ambil TOP N debitur NPL dengan outstanding terbesar → INDIVIDUAL.
        $nplIndexes = [];
        foreach ($rows as $i => $r) {
            if ($r['is_npl']) {
                $nplIndexes[] = $i;
            }
        }

        usort($nplIndexes, fn ($a, $b) => $rows[$b]['outstanding'] <=> $rows[$a]['outstanding']);
        $selected = array_slice($nplIndexes, 0, $topN);

        foreach ($selected as $rank => $idx) {
            $rows[$idx]['_individual'] = true;
            $rows[$idx]['_reason']     = $this->individualReason($rows[$idx], $rank + 1, $nplRatings);
        }

        // 3) Persist: INDIVIDUAL (TOP N NPL) + COLLECTIVE (sisanya).
        $this->cleanStage($period->id, ['stage_classifications']);

        $now         = now();
        $insertBatch = [];
        $batchSize   = 200;
        $totalIndi   = 0;
        $totalColl   = 0;

        foreach ($rows as $r) {
            $isIndividual = $r['_individual'];
            $isIndividual ? $totalIndi++ : $totalColl++;

            $insertBatch[] = [
                'assessment_period_id' => $period->id,
                'loan_number'          => $r['snapshot']->loan_number,
                'office_code'          => $r['snapshot']->office_code,
                'akad_code'            => $r['akad_code'],
                'usage_code'           => $r['usage_code'],
                'ead_amount'           => $r['ead'],
                'classification'       => $isIndividual ? 'INDIVIDUAL' : 'COLLECTIVE',
                'reason'               => $isIndividual
                    ? $r['_reason']
                    : 'collective_default (non-NPL atau di luar TOP ' . $topN . ' NPL)',
                'applied_rule'         => $isIndividual ? 'NPL_TOP_N_OUTSTANDING' : 'COLLECTIVE_DEFAULT',
                'created_at'           => $now,
                'updated_at'           => $now,
            ];

            if (count($insertBatch) >= $batchSize) {
                $this->safeInsert('stage_classifications', $insertBatch);
                $insertBatch = [];
            }
        }

        if (! empty($insertBatch)) {
            $this->safeInsert('stage_classifications', $insertBatch);
        }

        $this->audit('CLASSIFICATION', 'RUN', $period->id, [
            'total'           => count($rows),
            'npl_candidates'  => count($nplIndexes),
            'individual'      => $totalIndi,
            'collective'      => $totalColl,
            'total_ead'       => array_sum(array_column($rows, 'ead')),
            'buckets_assigned'=> $bucketsAssigned,
            'individual_top_n'=> $topN,
            'npl_ratings'     => $nplRatings,
            'dims'            => implode('|', $dims),
        ]);

        return $this->backTo($request, 'calculation.module.classification', [
            'total'          => count($rows),
            'npl_candidates' => count($nplIndexes),
            'individual'     => $totalIndi,
            'collective'     => $totalColl,
            'total_ead'      => array_sum(array_column($rows, 'ead')),
            'top_n'          => $topN,
            'npl_ratings'    => implode(',', $nplRatings),
            'dims'           => implode('|', $dims),
        ]);
    }

    private function countsFor(int $periodId): array
    {
        $rows = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->selectRaw('classification, COUNT(*) AS cnt, SUM(ead_amount) AS total_ead')
            ->groupBy('classification')
            ->get()
            ->keyBy('classification');

        return [
            'INDIVIDUAL' => (int) ($rows['INDIVIDUAL']->cnt ?? 0),
            'COLLECTIVE' => (int) ($rows['COLLECTIVE']->cnt ?? 0),
            'INDIVIDUAL_EAD' => (float) ($rows['INDIVIDUAL']->total_ead ?? 0),
            'COLLECTIVE_EAD' => (float) ($rows['COLLECTIVE']->total_ead ?? 0),
            'TOTAL_EAD' => (float) ($rows['INDIVIDUAL']->total_ead ?? 0) + (float) ($rows['COLLECTIVE']->total_ead ?? 0),
        ];
    }

    private function preview($period, array $config): \Illuminate\Support\Collection
    {
        return DB::table('stage_classifications')
            ->where('assessment_period_id', $period->id)
            ->orderBy('classification')
            ->orderBy('ead_amount', 'desc')
            ->limit(50)
            ->get();
    }

    /**
     * Alasan loan masuk INDIVIDUAL — jejak kualitas (kolektibilitas) + peringkat outstanding.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, int>  $nplRatings
     */
    private function individualReason(array $row, int $rank, array $nplRatings): string
    {
        $criteria = [];
        if (! empty($row['snapshot']->write_off_flag)) {
            $criteria[] = 'write_off_flag=true';
        }
        if (in_array($row['rating'], $nplRatings, true)) {
            $criteria[] = 'kualitas=' . $row['rating'];
        }

        $basis = $criteria ? implode('|', $criteria) : 'npl';

        return 'NPL(' . $basis . ') · rank#' . $rank
            . ' outstanding=' . number_format($row['outstanding'], 0, ',', '.');
    }
}