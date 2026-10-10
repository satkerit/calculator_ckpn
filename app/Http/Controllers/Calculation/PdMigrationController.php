<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Snapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul 4: PD Migration (Quality Rating).
 *
 * Hitung PD per grup segmentasi dengan migration matrix 5x5 dari perubahan
 * quality_rating antar periode historis.
 */
class PdMigrationController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period  = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config  = $this->configFor($period->id);
        $results = $this->loadResults($period->id);

        return view('calculation.modules.pd_migration', compact('period', 'periods', 'config', 'results'));
    }

    public function run(Request $request): RedirectResponse
    {
        $period = $this->resolvePeriod($request);
        $config   = $this->configFor($period->id);
        $histM    = (int) $config['historical_months'];
        $dims     = $this->segmentDimensions($request, $period->id);
        $assessmentDate = $period->assessment_date;
        $startPeriod = $assessmentDate->copy()->subMonths($histM)->startOfMonth();

        // Hanya hapus hasil MIGRATION (jangan sentuh NET_FLOW periode ini).
        DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'MIGRATION')
            ->delete();

        // Default bucket kolektif: rating >= 4 dianggap default.
        $groups = $this->loadCollectiveGroups($period->id, $dims)->map(function ($g) use ($dims) {
            $g->group_key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);
            return $g;
        });

        if ($groups->isEmpty()) {
            return $this->backTo($request, 'calculation.module.pd_migration', [], 'Belum ada klasifikasi COLLECTIVE.');
        }

        $loanNumbers = DB::table('stage_classifications')
            ->where('assessment_period_id', $period->id)
            ->where('classification', 'COLLECTIVE')
            ->pluck('loan_number');

        $cursor = $startPeriod->copy();
        $allPeriods = [];
        while ($cursor->format('Ym') <= $assessmentDate->format('Ym')) {
            $allPeriods[] = $cursor->format('Ym');
            $cursor->addMonth();
        }

        $snaps = Snapshot::whereIn('loan_number', $loanNumbers)
            ->whereIn('periode_snapshot', $allPeriods)
            ->get()
            ->groupBy(fn ($s) => $s->periode_snapshot . '|' . $s->loan_number);

        $now   = now();
        $batch = [];

        foreach ($groups as $g) {
            $pdRate = $this->computeMigrationPd($snaps, $g, $allPeriods, $period->id);

            $batch[] = [
                'assessment_period_id' => $period->id,
                'group_key'            => $g->group_key,
                'pd_calculated'        => round($pdRate, 6),
                'method'               => 'MIGRATION',
                'periods_analyzed'     => max(0, count($allPeriods) - 1),
                'detail'               => json_encode(['method' => 'MIGRATION', 'dims' => implode('|', $dims)]),
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        // Replace existing MIGRATION rows for this period.
        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_pd_results', $chunk);
        }

        $this->audit('PD_MIGRATION', 'RUN', $period->id, [
            'groups' => $groups->count(),
            'window' => $histM,
            'dims'   => implode('|', $dims),
        ]);

        return $this->backTo($request, 'calculation.module.pd_migration', [
            'groups'  => $groups->count(),
            'method'  => 'MIGRATION',
            'window'  => $histM . ' bln',
        ]);
    }

    /**
     * Hitung PD via migration matrix (default state = rating >= 4).
     */
    private function computeMigrationPd($snaps, object $group, array $periods, int $periodId): float
    {
        $loanNumbers = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'COLLECTIVE')
            ->where('office_code', $group->office_code)
            ->where('akad_code',   $group->akad_code)
            ->where('usage_code',  $group->usage_code)
            ->pluck('loan_number');

        if ($loanNumbers->isEmpty()) {
            return 0.0;
        }

        $migration = [];
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $curP  = $periods[$i];
            $nextP = $periods[$i + 1];

            foreach ($loanNumbers as $ln) {
                $curRows  = $snaps->get($curP.'|'.$ln);
                $nextRows = $snaps->get($nextP.'|'.$ln);
                $cur  = $curRows instanceof \Illuminate\Support\Collection ? $curRows->first() : $curRows;
                $next = $nextRows instanceof \Illuminate\Support\Collection ? $nextRows->first() : $nextRows;
                if (! $cur || ! $next) {
                    continue;
                }
                $from = $this->ratingBucket($cur->quality_rating);
                $to   = $this->ratingBucket($next->quality_rating);
                $migration[$from][$to] = ($migration[$from][$to] ?? 0) + 1;
            }
        }

        if (empty($migration)) {
            return 0.0;
        }

        // Default state: rating bucket 3,4 = default.
        $defaultBuckets = [3, 4];
        $pd = 0.0;
        $totalFrom = 0;

        foreach ($migration as $from => $tos) {
            $rowSum = array_sum($tos);
            $totalFrom += $rowSum;
            $loss = 0;
            foreach ($defaultBuckets as $db) {
                $loss += $tos[$db] ?? 0;
            }
            $pd += $loss;
        }

        return $totalFrom > 0 ? min(1.0, max(0.0, $pd / $totalFrom)) : 0.0;
    }

    /**
     * Map quality_rating ke 5 bucket: 1=lancar, 2=DPK, 3=mgd, 4=macet, 5=WO.
     */
    private function ratingBucket(?string $rating): int
    {
        $r = (int) ($rating ?? 1);
        return max(1, min(5, $r));
    }

    private function loadResults(int $periodId): array
    {
        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'MIGRATION')
            ->orderBy('pd_calculated', 'desc')
            ->get();

        $avg = $rows->isEmpty() ? 0.0 : (float) $rows->avg('pd_calculated');

        return [
            'rows'   => $rows,
            'count'  => $rows->count(),
            'avg_pd' => round($avg, 6),
            'method' => 'MIGRATION',
        ];
    }
}