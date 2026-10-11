<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Snapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $period = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config = $this->configFor($period->id);
        $results = $this->loadResults($period->id);
        // Hasil basi bila konfigurasi (segmentasi/window/metode PD) berubah setelah run terakhir.
        $pdStale = in_array('PD', $this->staleResultTypes($period->id), true);

        return view('calculation.modules.pd_migration', compact('period', 'periods', 'config', 'results', 'pdStale'));
    }

    public function run(Request $request): RedirectResponse|JsonResponse
    {
        $period = $this->resolvePeriod($request);

        // Lock per-periode + transaksi agar penulisan hasil atomik & tidak bentrok antar user.
        return $this->withRunLock($period->id, fn () => DB::transaction(function () use ($request, $period) {
            $config = $this->configFor($period->id);
            $histM = (int) $config['historical_months'];
            $dims = $this->segmentDimensions($request, $period->id);
            $assessmentDate = $period->assessment_date;
            $startPeriod = $assessmentDate->copy()->subMonths($histM)->startOfMonth();

            // Hanya hapus hasil MIGRATION (jangan sentuh NET_FLOW periode ini).
            DB::table('stage_pd_results')
                ->where('assessment_period_id', $period->id)
                ->where('method', 'MIGRATION')
                ->delete();

            $groups = $this->loadCollectiveGroups($period->id, $dims)->map(function ($g) use ($dims) {
                $g->group_key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);

                return $g;
            });

            if ($groups->isEmpty()) {
                return $this->backTo($request, 'calculation.module.pd_migration', [], 'Belum ada klasifikasi COLLECTIVE.');
            }

            $token = $this->progressToken($request);
            $this->pushProgress($token, 'Memuat snapshot historis…', 5);

            // N+1 fix: muat member per grup sekali (in-memory) alih-alih query per grup.
            $classRows = DB::table('stage_classifications')
                ->where('assessment_period_id', $period->id)
                ->where('classification', 'COLLECTIVE')
                ->get(['loan_number', 'office_code', 'akad_code', 'usage_code']);

            $loansByGroup = [];
            foreach ($classRows as $c) {
                $loansByGroup[$this->collectiveGroupKey($dims, $c->office_code, $c->akad_code, $c->usage_code)][] = $c->loan_number;
            }
            $loanNumbers = $classRows->pluck('loan_number')->unique()->values();

            $cursor = $startPeriod->copy();
            $allPeriods = [];
            while ($cursor->format('Ym') <= $assessmentDate->format('Ym')) {
                $allPeriods[] = $cursor->format('Ym');
                $cursor->addMonth();
            }

            $this->pushProgress($token, 'Memuat snapshot historis…', 15);

            // Filter: hanya snapshot pembiayaan aktif.
            $snaps = Snapshot::whereIn('loan_number', $loanNumbers)
                ->whereIn('periode_snapshot', $allPeriods)
                ->where('active_flag', true)
                ->get()
                ->groupBy(fn ($s) => $s->periode_snapshot.'|'.$s->loan_number);

            $now = now();
            $batch = [];
            $totalGroups = max(1, $groups->count());
            $i = 0;

            foreach ($groups as $g) {
                $i++;
                $this->pushProgress($token, "Menghitung matriks migrasi rating grup {$i}/{$totalGroups} ({$g->group_key})…", 15 + ($i / $totalGroups) * 75, [
                    'current' => $i, 'total' => $totalGroups, 'label' => 'Hitung PD Migration',
                ]);
                $pdRate = $this->computeMigrationPd($snaps, $allPeriods, $loansByGroup[$g->group_key] ?? []);

                $batch[] = [
                    'assessment_period_id' => $period->id,
                    'group_key' => $g->group_key,
                    'pd_calculated' => round($pdRate, 6),
                    'method' => 'MIGRATION',
                    'periods_analyzed' => max(0, count($allPeriods) - 1),
                    'detail' => json_encode(['method' => 'MIGRATION', 'dims' => implode('|', $dims)]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Replace existing MIGRATION rows for this period.
            $this->pushProgress($token, 'Menyimpan hasil PD Migration…', 95);
            foreach (array_chunk($batch, 200) as $chunk) {
                $this->safeInsert('stage_pd_results', $chunk);
            }

            // Tandai hasil PD sah dengan konfigurasi yang dipakai saat run (fingerprint).
            $this->markResults($period->id, 'PD', ['segment_dimensions' => $dims]);

            $this->audit('PD_MIGRATION', 'RUN', $period->id, [
                'groups' => $groups->count(),
                'window' => $histM,
                'inserted' => count($batch),
            ]);

            return $this->backTo($request, 'calculation.module.pd_migration', [
                'groups' => $groups->count(),
                'method' => 'MIGRATION',
                'window' => $histM.' bln',
            ]);
        }));
    }

    /**
     * Hitung PD via migration matrix rating antar periode historis.
     * State default: rating 4 (macet) dan 5 (WO) — konsisten dengan spec.
     */
    private function computeMigrationPd(Collection $snaps, array $periods, array $loanNumbers): float
    {
        $migration = [];
        for ($i = 0; $i < count($periods) - 1; $i++) {
            $curP = $periods[$i];
            $nextP = $periods[$i + 1];

            foreach ($loanNumbers as $ln) {
                $curRows = $snaps->get($curP.'|'.$ln);
                $nextRows = $snaps->get($nextP.'|'.$ln);
                $cur = $curRows instanceof Collection ? $curRows->first() : $curRows;
                $next = $nextRows instanceof Collection ? $nextRows->first() : $nextRows;
                if (! $cur || ! $next) {
                    continue;
                }
                $from = $this->ratingBucket($cur->quality_rating);
                $to = $this->ratingBucket($next->quality_rating);
                $migration[$from][$to] = ($migration[$from][$to] ?? 0) + 1;
            }
        }

        if (empty($migration)) {
            return 0.0;
        }

        // State default: rating 4 (macet), 5, dan WO (tulis-off) — konsisten dengan spec.
        $defaultBuckets = [4, 5];
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
            'rows' => $rows,
            'count' => $rows->count(),
            'avg_pd' => round($avg, 6),
            'method' => 'MIGRATION',
        ];
    }
}
