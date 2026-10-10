<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Snapshot;
use App\Services\Bucket\BucketAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul 3: PD Net Flow.
 *
 * Hitung PD per grup segmentasi dengan metode Net Flow: EAD-weighted share
 * di bucket loss (≥ 7 = 210+ hari) ditambah compound dari migration matrix
 * historis bila periode historis cukup.
 */
class PdNetFlowController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period  = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config  = $this->configFor($period->id);
        $results = $this->loadResults($period->id);

        return view('calculation.modules.pd_netflow', compact('period', 'periods', 'config', 'results'));
    }

    public function run(Request $request): RedirectResponse
    {
        $period = $this->resolvePeriod($request);
        $config   = $this->configFor($period->id);
        $histM    = (int) $config['historical_months'];
        $dims     = $this->segmentDimensions($request, $period->id);
        $assessmentDate = $period->assessment_date;
        $startPeriod = $assessmentDate->copy()->subMonths($histM)->startOfMonth();

        $this->cleanStage($period->id, ['stage_pd_results', 'stage_segmented_snapshots', 'stage_historical_buckets', 'stage_migration_rates', 'stage_compound_flow']);

        // 1) Hitung PD per grup via bucket distribution (proxy) ATAU compound flow.
        $groups = $this->loadGroups($period->id, $dims);
        if ($groups->isEmpty()) {
            return $this->backTo($request, 'calculation.module.pd_netflow', [], 'Belum ada klasifikasi COLLECTIVE. Jalankan Modul 2 dulu.');
        }

        // 1) Panggil PdNetflowCalculator untuk menghitung 3 tabel (Bucket Snapshots, Transitions, Compound Flow)
        $calculator = app(\App\Services\PdNetflow\PdNetflowCalculator::class);
        $service    = app(\App\Services\PdNetflow\PdNetflowService::class);

        foreach ($groups as $g) {
            $parts = explode('|', $g->group_key);
            $paddedKey = implode('|', [
                $parts[0] ?? 'NA',
                $parts[1] ?? 'NA',
                $parts[2] ?? 'NA',
            ]);
            $calculator->calculate($period->id, $paddedKey);
        }

        // 2) Simpan PD final (PD Rate dari Bucket 1 Tabel 3 Compound Flow) ke stage_pd_results
        DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'NET_FLOW')
            ->delete();

        $batch = [];
        $now   = now();
        foreach ($groups as $g) {
            $parts = explode('|', $g->group_key);
            $paddedKey = implode('|', [
                $parts[0] ?? 'NA',
                $parts[1] ?? 'NA',
                $parts[2] ?? 'NA',
            ]);

            $compoundData = $service->loadCompoundFlows($period->id, $paddedKey);
            // Ambil PD Rate dari Bucket 1 (Lancar / 0 hari) — titik awal peminjam.
            $pdRate = (float) ($compoundData[1]['pd_rate'] ?? 0.0);
            $pdRate = min(1.0, max(0.0, $pdRate));

            $batch[] = [
                'assessment_period_id' => $period->id,
                'group_key'            => $g->group_key,
                'pd_calculated'        => round($pdRate, 6),
                'method'               => 'NET_FLOW',
                'periods_analyzed'     => $histM,
                'detail'               => json_encode([
                    'method'           => 'NET_FLOW_ENGINE',
                    'source_bucket'    => 1,
                    'pd_rate'          => round($pdRate, 6),
                    'total_ead'        => round((float) $g->total_ead, 2),
                    'member_count'     => (int) $g->member_count,
                ]),
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_pd_results', $chunk);
        }

        $this->audit('PD_NETFLOW', 'RUN', $period->id, [
            'groups' => $groups->count(),
            'window' => $histM,
            'dims'   => implode('|', $dims),
        ]);

        // Redirect dgn show=1 agar setelah kalkulasi langsung masuk state "data tampil".
        $redirect = redirect()->route('calculation.module.pd_netflow', [
            'assessment_period_id' => $period->id,
            'show'                 => 1,
        ]);

        return $redirect->with('success', $this->formatSummary([
            'groups'  => $groups->count(),
            'method'  => 'NET_FLOW',
            'window'  => $histM . ' bln',
        ]))->with('result', [
            'groups'  => $groups->count(),
            'method'  => 'NET_FLOW',
            'window'  => $histM . ' bln',
        ]);
    }

    private function loadGroups(int $periodId, array $dims): \Illuminate\Support\Collection
    {
        $query = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'COLLECTIVE');

        $rows = $query
            ->selectRaw('office_code, akad_code, usage_code, COUNT(*) as member_count, SUM(ead_amount) as total_ead')
            ->groupBy('office_code', 'akad_code', 'usage_code')
            ->get();

        $groups = $rows->map(function ($g) use ($dims) {
            $seg = $this->buildSegment($dims, $g->office_code, $g->akad_code, $g->usage_code);
            $g->group_key = $seg['group_key'];
            $g->segment   = $seg['segments'];
            return $g;
        });

        // Group by group_key agar count & iterasi sesuai dimensi yang dipilih
        return $groups->groupBy('group_key')->map(function ($subGroups, $groupKey) {
            $first = $subGroups->first();
            $first->member_count = $subGroups->sum('member_count');
            $first->total_ead = $subGroups->sum('total_ead');
            $first->sub_groups = $subGroups; // simpan referensi detail 3 dimensi
            return $first;
        })->values();
    }

    private function buildSegmentedSnapshots($period, $startPeriod, $groups, array $dims = []): void
    {
        $members = DB::table('stage_classifications')
            ->where('assessment_period_id', $period->id)
            ->where('classification', 'COLLECTIVE')
            ->select('loan_number', 'office_code', 'akad_code', 'usage_code')
            ->get()
            ->keyBy('loan_number');

        $loanNumbers = $members->keys();
        if ($loanNumbers->isEmpty()) {
            return;
        }

        // Build period list: EXACTLY 37 snapshot periods (N..N-36) so that we get 36 transition pairs (N-36->N-35 ... N-1->N)
        $cursor = clone $startPeriod;
        $periods = [];
        $endMonthStr = $period->assessment_date->format('Ym');
        
        while ($cursor->format('Ym') <= $endMonthStr) {
            $periods[] = $cursor->format('Ym');
            $cursor->addMonth();
        }
        if (empty($periods)) {
            return;
        }

        $snaps = Snapshot::whereIn('loan_number', $loanNumbers)
            ->whereIn('periode_snapshot', $periods)
            ->get()
            ->groupBy(fn ($s) => $s->periode_snapshot . '|' . $s->loan_number);

        $bucketService = app(\App\Services\Bucket\BucketAssignmentService::class);
        $now         = now();
        $insertBatch = [];

        foreach ($groups as $g) {
            $matchingOffice = in_array('OFFICE', $dims);
            $matchingAkad   = in_array('AKAD', $dims);
            $matchingUsage  = in_array('USAGE', $dims);

            $membersInGroup = $members->filter(function ($m) use ($g, $matchingOffice, $matchingAkad, $matchingUsage) {
                $match = true;
                if ($matchingOffice && $m->office_code !== $g->office_code) $match = false;
                if ($matchingAkad && $m->akad_code !== $g->akad_code) $match = false;
                if ($matchingUsage && $m->usage_code !== $g->usage_code) $match = false;
                return $match;
            });

            foreach ($periods as $periodeYm) {
                foreach ($membersInGroup as $ln => $m) {
                    $hs = $snaps->get($periodeYm.'|'.$ln);
                    $snap = $hs instanceof \Illuminate\Support\Collection ? $hs->first() : $hs;
                    $bucketNo = $snap ? $bucketService->calculateBucket($snap) : 1;

                    $insertBatch[] = [
                        'assessment_period_id' => $period->id,
                        'group_key'            => $g->group_key,
                        'periode_snapshot'     => $periodeYm,
                        'loan_number'          => $ln,
                        'bucket_no'            => $bucketNo,
                        'ead'                  => round((float) ($snap->outstanding_balance ?? 0) + (float) ($snap->principal_arrears ?? 0), 2),
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];
                }
            }
        }

        // Chunk insert
        foreach (array_chunk($insertBatch, 500) as $chunk) {
            $this->safeInsert('stage_segmented_snapshots', $chunk);
        }
    }

    private function aggregateHistoricalBuckets(int $periodId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stage_historical_buckets')
            || ! \Illuminate\Support\Facades\Schema::hasTable('stage_segmented_snapshots')) {
            return;
        }
        DB::table('stage_historical_buckets')
            ->where('assessment_period_id', $periodId)
            ->delete();

        $segments = DB::table('stage_segmented_snapshots')
            ->where('assessment_period_id', $periodId)
            ->selectRaw('group_key, periode_snapshot, bucket_no, COUNT(*) as member_count, SUM(ead) as total_ead')
            ->groupBy('group_key', 'periode_snapshot', 'bucket_no')
            ->get();

        $now = now();
        foreach ($segments as $s) {
            DB::table('stage_historical_buckets')->insert([
                'assessment_period_id' => $periodId,
                'group_key'            => $s->group_key,
                'periode_snapshot'     => $s->periode_snapshot,
                'bucket_no'            => $s->bucket_no,
                'hari_tunggakan'       => '0',
                'ead'                  => round((float) $s->total_ead, 2),
                'member_count'         => (int) $s->member_count,
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);
        }
    }

    private function computeMigrationRates(int $periodId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stage_migration_rates')
            || ! \Illuminate\Support\Facades\Schema::hasTable('stage_segmented_snapshots')) {
            return;
        }
        DB::table('stage_migration_rates')
            ->where('assessment_period_id', $periodId)
            ->delete();

        $rows = DB::table('stage_segmented_snapshots')
            ->where('assessment_period_id', $periodId)
            ->select('group_key', 'periode_snapshot', 'loan_number', 'bucket_no')
            ->get()
            ->groupBy('group_key');

        $now = now();
        $batch = [];

        foreach ($rows as $groupKey => $group) {
            $byPeriod = $group->groupBy('periode_snapshot')->sortKeys();
            $periods = $byPeriod->keys()->values();
            for ($p = 0; $p < $periods->count() - 1; $p++) {
                $cur  = $byPeriod[$periods[$p]]->keyBy('loan_number');
                $next = $byPeriod[$periods[$p + 1]]->keyBy('loan_number');

                foreach ($cur->groupBy('bucket_no') as $fromBucket => $loans) {
                    $fromCount = $loans->count();
                    if ($fromCount === 0) {
                        continue;
                    }
                    $transitions = [];
                    // groupBy()_me-reset key, jadi loan_number diambil dari kolom row.
                    foreach ($loans as $row) {
                        $ln = $row->loan_number;
                        if (! $next->has($ln)) {
                            continue;
                        }
                        $toBucket = (int) $next->get($ln)->bucket_no;
                        $transitions[$toBucket] = ($transitions[$toBucket] ?? 0) + 1;
                    }
                    foreach ($transitions as $toBucket => $cnt) {
                        $batch[] = [
                            'assessment_period_id' => $periodId,
                            'group_key'            => $groupKey,
                            'periode_snapshot'     => $periods[$p],
                            'from_bucket'          => (int) $fromBucket,
                            'to_bucket'            => (int) $toBucket,
                            'rate'                 => round($cnt / $fromCount, 6),
                            'created_at'           => $now,
                            'updated_at'           => $now,
                        ];
                    }
                }
            }
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_migration_rates', $chunk);
        }
    }

    private function computeCompoundFlow(int $periodId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stage_compound_flow')
            || ! \Illuminate\Support\Facades\Schema::hasTable('stage_migration_rates')) {
            return;
        }
        DB::table('stage_compound_flow')
            ->where('assessment_period_id', $periodId)
            ->delete();

        $rows = DB::table('stage_migration_rates')
            ->where('assessment_period_id', $periodId)
            ->orderBy('group_key')
            ->orderBy('from_bucket')
            ->orderBy('periode_snapshot')
            ->get()
            ->groupBy('group_key');

        $now = now();
        $batch = [];

        foreach ($rows as $groupKey => $rates) {
            foreach ($rates->groupBy('from_bucket') as $fromBucket => $bucketRates) {
                $compound = 1.0;
                foreach ($bucketRates as $r) {
                    $compound *= (1 - (float) $r->rate);
                }
                $loss = min(1.0, max(0.0, 1 - $compound));
                $batch[] = [
                    'assessment_period_id' => $periodId,
                    'group_key'            => $groupKey,
                    'from_bucket'          => (int) $fromBucket,
                    'periode_snapshot'     => $bucketRates->last()->periode_snapshot,
                    'compound_loss'        => round($loss, 6),
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ];
            }
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_compound_flow', $chunk);
        }
    }

    private function finalizePd(int $periodId): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('stage_segmented_snapshots')) {
            return;
        }

        // Net flow = EAD-weighted share loan pada bucket loss (>= 7) pada periode
        // snapshot TERAKHIR. Rata-rata compound_loss seluruh bucket akan mengencerkan
        // PD karena bucket 1 (lancar) selalu ada dan kontribusinya besar.
        $lossBucketFrom = 7;

        $latestYm = DB::table('stage_segmented_snapshots')
            ->where('assessment_period_id', $periodId)
            ->max('periode_snapshot');

        $finalPd = DB::table('stage_segmented_snapshots')
            ->where('assessment_period_id', $periodId)
            ->when($latestYm, fn ($q) => $q->where('periode_snapshot', $latestYm))
            ->selectRaw(
                'group_key,'.
                'SUM(CASE WHEN bucket_no >= ? THEN ead ELSE 0 END) as loss_ead,'.
                'SUM(ead) as total_ead,'.
                'COUNT(*) as accounts,'.
                'SUM(CASE WHEN bucket_no >= ? THEN 1 ELSE 0 END) as loss_accounts',
                [$lossBucketFrom, $lossBucketFrom],
            )
            ->groupBy('group_key')
            ->get();

        $periodsAnalyzed = DB::table('stage_segmented_snapshots')
            ->where('assessment_period_id', $periodId)
            ->distinct()
            ->count('periode_snapshot');

        $now = now();
        $batch = [];
        foreach ($finalPd as $r) {
            $totalEad = (float) $r->total_ead;
            $pd = $totalEad > 0
                ? (float) $r->loss_ead / $totalEad
                : ((int) $r->accounts > 0 ? (int) $r->loss_accounts / (int) $r->accounts : 0.0);

            $batch[] = [
                'assessment_period_id' => $periodId,
                'group_key'            => $r->group_key,
                'pd_calculated'        => round(min(1.0, max(0.0, $pd)), 6),
                'method'               => 'NET_FLOW',
                'periods_analyzed'     => (int) $periodsAnalyzed,
                'detail'               => json_encode([
                    'method'          => 'NET_FLOW',
                    'source'          => 'stage_segmented_snapshots',
                    'periode_snapshot'=> $latestYm,
                    'loss_bucket_from'=> $lossBucketFrom,
                    'total_ead'       => round($totalEad, 2),
                    'loss_ead'        => round((float) $r->loss_ead, 2),
                    'accounts'        => (int) $r->accounts,
                    'loss_accounts'   => (int) $r->loss_accounts,
                ]),
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }
        // Bersihkan PD lama periode ini.
        DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'NET_FLOW')
            ->delete();

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_pd_results', $chunk);
        }
    }

    private function loadResults(int $periodId): array
    {
        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'NET_FLOW')
            ->orderBy('pd_calculated', 'desc')
            ->get();

        $avg = $rows->isEmpty() ? 0.0 : (float) $rows->avg('pd_calculated');

        return [
            'rows'      => $rows,
            'count'     => $rows->count(),
            'avg_pd'    => round($avg, 6),
            'method'    => 'NET_FLOW',
        ];
    }
}