<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Collateral;
use App\Services\Ckpn\CkpnCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Modul 8: Detail CKPN (Individual + Kolektif).
 *
 * Menghitung CKPN final:
 * - Individual: nilai_tercatat - net_recovery_value (liquidity * (1 - sale_cost))
 * - Kolektif  : EAD × PD × LGD (LGD per metode konfigurasi: ER / CS / WEIGHTED)
 */
class DetailCkpnController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config = $this->configFor($period->id);

        $individuals = $this->individualRows($period->id);
        $collectives = $this->collectiveRows($period->id);
        $summary = $this->summary($period->id);
        // Hasil basi: konfigurasi berubah setelah PD/LGD/CKPN terakhir dihitung.
        $stale = $this->staleResultTypes($period->id);

        return view('calculation.modules.detail_ckpn', compact(
            'period',
            'periods',
            'config',
            'individuals',
            'collectives',
            'summary',
            'stale',
        ));
    }

    public function finalize(Request $request): RedirectResponse|JsonResponse
    {
        $period = $this->resolvePeriod($request);
        $config = $this->configFor($period->id);

        // CKPN hanya boleh dihitung setelah PD dan LGD tersedia untuk periode ini.
        // PD harus ada sesuai metode aktif di konfigurasi (NET_FLOW / MIGRATION).
        $pdCount = DB::table('stage_pd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $config['pd_method'])
            ->count();
        $lgdCount = DB::table('stage_lgd_results')->where('assessment_period_id', $period->id)->count();
        $classCount = DB::table('stage_classifications')->where('assessment_period_id', $period->id)->count();

        if ($classCount === 0) {
            return $this->backTo($request, 'calculation.module.detail_ckpn', [], 'Klasifikasi belum dijalankan. Jalankan modul Classification terlebih dahulu.');
        }
        if ($pdCount === 0) {
            return $this->backTo($request, 'calculation.module.detail_ckpn', [], "Nilai PD dengan metode {$config['pd_method']} belum tersedia. Jalankan modul PD Net Flow / Migration sesuai metode konfigurasi terlebih dahulu.");
        }
        if ($lgdCount === 0) {
            return $this->backTo($request, 'calculation.module.detail_ckpn', [], 'Nilai LGD belum tersedia. Jalankan modul LGD (ER dan/atau CS) terlebih dahulu.');
        }

        // Finalize DIBLOKIR bila konfigurasi berubah setelah PD/LGD dihitung
        // (mis. segmentasi ulang) — hasil lama tidak akurat dan wajib dihitung ulang.
        // (CKPN sendiri tidak memblokir: finalize adalah cara menghitung ulang CKPN.)
        $stale = $this->staleResultTypes($period->id);
        $blocking = array_values(array_intersect($stale, ['PD', 'LGD']));
        if ($blocking !== []) {
            $labels = implode(', ', array_map(fn ($t) => $this->staleResultLabel($t), $blocking));

            return $this->backTo(
                $request,
                'calculation.module.detail_ckpn',
                [],
                "Konfigurasi berubah setelah perhitungan terakhir (tidak valid: {$labels}). Hitung ulang modul tersebut terlebih dahulu sebelum Finalize."
            );
        }

        $token = $this->progressToken($request);

        // Lock per-periode: cegah dua user finalize periode sama bersamaan.
        return $this->withRunLock($period->id, function () use ($request, $period, $config, $token) {
            // Transaksi: penulisan individual + kolektif + konsolidasi atomik.
            return DB::transaction(function () use ($request, $period, $config, $token) {
                // Hitung ulang individual + collective untuk konsistensi.
                $this->pushProgress($token, 'Menghitung CKPN Individual…', 10);
                $this->computeIndividual($period->id, $config, $token);
                $this->pushProgress($token, 'Menghitung CKPN Kolektif (EAD × PD × LGD)…', 55);
                $this->computeCollective($period->id, $config, $token);
                $this->pushProgress($token, 'Konsolidasi total CKPN…', 92);

                $summary = $this->summary($period->id);

                // Tandai hasil CKPN sah dengan konfigurasi berlaku (fingerprint).
                $this->markResults($period->id, 'CKPN');

                $this->audit('CKPN', 'FINALIZE', $period->id, $summary);

                return $this->backTo($request, 'calculation.module.detail_ckpn', [
                    'finalized' => 1,
                    'individual_count' => $summary['count_individual'],
                    'collective_count' => $summary['count_collective'],
                    'total_ckpn' => $summary['total_ckpn'],
                ]);
            });
        });
    }

    private function computeIndividual(int $periodId, array $config, ?string $token = null): void
    {
        if (! Schema::hasTable('stage_individual_ckpn')) {
            return;
        }
        DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->delete();

        $individuals = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'INDIVIDUAL')
            ->get();

        if ($individuals->isEmpty()) {
            return;
        }

        $saleCostPct = (float) $config['sale_cost_percentage'];

        $collateralMap = Collateral::whereIn('loan_number', $individuals->pluck('loan_number'))
            ->groupBy('loan_number')
            ->selectRaw('loan_number, SUM(liquidity_value) as total_liquidity')
            ->pluck('total_liquidity', 'loan_number')
            ->map(fn ($v) => (float) $v)
            ->toArray();

        $now = now();
        $batch = [];
        $totalInd = max(1, $individuals->count());
        $indIdx = 0;

        foreach ($individuals as $ind) {
            $indIdx++;
            if ($token !== null && ($indIdx % 50 === 0 || $indIdx === $totalInd)) {
                $this->pushProgress($token, "Menghitung CKPN Individual rekening {$indIdx}/{$totalInd}…", 10 + ($indIdx / $totalInd) * 40, [
                    'current' => $indIdx, 'total' => $totalInd, 'label' => 'CKPN Individual',
                ]);
            }
            $loanNumber = $ind->loan_number;
            $liquidity = (float) ($collateralMap[$loanNumber] ?? 0);
            $nilai = (float) $ind->ead_amount;
            $saleCost = round($liquidity * $saleCostPct, 2);

            $ckpn = CkpnCalculator::calculateIndividual($nilai, $liquidity, $saleCostPct);

            $batch[] = [
                'assessment_period_id' => $periodId,
                'loan_number' => $loanNumber,
                'nilai_tercatat' => $nilai,
                'liquidity_value' => round($liquidity, 2),
                'sale_cost' => $saleCost,
                'estimated_recovery' => round($liquidity - $saleCost, 2),
                'ckpn_individual' => $ckpn,
                'recovery_method' => $liquidity > 0 ? 'COLLATERAL_LIQUIDITY' : 'NO_COLLATERAL',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_individual_ckpn', $chunk);
        }
    }

    private function computeCollective(int $periodId, array $config, ?string $token = null): void
    {
        if (! Schema::hasTable('stage_collective_ckpn')) {
            return;
        }
        DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->delete();

        // EAD totals per grup: prefer stage_ead_buckets (post-bucket), fallback ke
        // stage_classifications (EAD dihitung saat klasifikasi).
        $eadGroups = $this->eadGroups($periodId);

        if ($eadGroups->isEmpty()) {
            return;
        }

        // PD per grup — filter sesuai metode aktif (NET_FLOW/MIGRATION), cegah PD tertukar.
        $pdMap = DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', $config['pd_method'])
            ->get()
            ->keyBy('group_key');

        // LGD per grup, mengikuti metode aktif di konfigurasi (lgd_method).
        $lgdEr = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'ER')
            ->get()
            ->keyBy('group_key');
        $lgdCs = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'CS')
            ->get()
            ->keyBy('group_key');

        // Metode LGD aktif: EXPECTED_RECOVERY | COLLATERAL_SHORTFALL | WEIGHTED.
        $lgdMethod = strtoupper((string) ($config['lgd_method'] ?? 'WEIGHTED'));
        $now = now();
        $batch = [];
        $totalGroups = max(1, $eadGroups->count());
        $groupIdx = 0;

        foreach ($eadGroups as $eg) {
            $groupIdx++;
            if ($token !== null) {
                $this->pushProgress($token, "Menghitung CKPN Kolektif grup {$groupIdx}/{$totalGroups} ({$eg->group_key})…", 55 + ($groupIdx / $totalGroups) * 35, [
                    'current' => $groupIdx, 'total' => $totalGroups, 'label' => 'CKPN Kolektif',
                ]);
            }
            $key = $eg->group_key;
            $pd = isset($pdMap[$key]) ? (float) $pdMap[$key]->pd_calculated : 0.0;
            $erObj = $lgdEr[$key] ?? null;
            $csObj = $lgdCs[$key] ?? null;

            $erVal = $erObj ? (float) $erObj->lgd_calculated : null;
            $csVal = $csObj ? (float) $csObj->lgd_calculated : null;

            // Kertas kerja LGD (B4.LGD) — nilai per metode sesuai konfigurasi:
            // - EXPECTED_RECOVERY   : LGD = 1 - recovery_rate = Loss_ER / Wo_ER
            // - COLLATERAL_SHORTFALL: LGD = Shortfall_CS / Exposure_CS
            // - WEIGHTED (default)  : Total Loss / Total Exposure
            //                         = (Loss_ER + Loss_CS) / (Wo_ER + Exposure_CS)
            $woEr   = $erObj ? (float) $erObj->total_write_off : 0.0;
            $lossEr = $erObj ? ($woEr - (float) $erObj->total_recovery) : 0.0;
            $expCs  = $csObj ? (float) $csObj->total_exposure_wo : 0.0;
            $lossCs = $csObj ? (float) $csObj->total_shortfall : 0.0;

            $lgd = match ($lgdMethod) {
                'EXPECTED_RECOVERY' => $erVal ?? $this->weightedLgd($woEr, $lossEr, $expCs, $lossCs) ?? 1.0,
                'COLLATERAL_SHORTFALL' => $csVal ?? $this->weightedLgd($woEr, $lossEr, $expCs, $lossCs) ?? 1.0,
                default => $this->weightedLgd($woEr, $lossEr, $expCs, $lossCs)
                    ?? ($erVal !== null && $csVal !== null ? ($erVal + $csVal) / 2 : ($erVal ?? $csVal ?? 1.0)),
            };
            $lgd = min(1.0, max(0.0, $lgd));
            $totalEad = (float) $eg->total_ead;

            $ckpn = CkpnCalculator::calculateCollective($pd, $lgd, $totalEad);

            $batch[] = [
                'assessment_period_id' => $periodId,
                'group_key' => $key,
                'office_code' => $eg->office_code,
                'akad_code' => $eg->akad_code,
                'usage_code' => $eg->usage_code,
                'total_ead' => round($totalEad, 2),
                'pd' => round($pd, 6),
                'lgd' => round($lgd, 6),
                'lgd_method' => $lgdMethod,
                'ckpn_kolektif' => $ckpn,
                'member_count' => (int) $eg->member_count,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_collective_ckpn', $chunk);
        }
    }

    /**
     * Weighted LGD sesuai kertas kerja: Total Loss / Total Exposure.
     * Null bila total exposure nol (caller menentukan fallback).
     */
    private function weightedLgd(float $woEr, float $lossEr, float $expCs, float $lossCs): ?float
    {
        $totalExp = $woEr + $expCs;
        if ($totalExp <= 0) {
            return null;
        }

        return (max(0.0, $lossEr) + max(0.0, $lossCs)) / $totalExp;
    }

    /**
     * EAD totals per grup segmentasi khusus untuk rekening COLLECTIVE.
     * Mengambil langsung dari stage_classifications agar sinkron dengan dimensi
     * segmentasi aktif dan group_key pada PD dan LGD.
     */
    private function eadGroups(int $periodId): Collection
    {
        $dims = $this->segmentDimensions(request(), $periodId);

        $rows = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'COLLECTIVE')
            ->selectRaw('office_code, akad_code, usage_code, SUM(ead_amount) as total_ead, COUNT(*) as member_count')
            ->groupBy('office_code', 'akad_code', 'usage_code')
            ->get();

        return $rows->map(function ($g) use ($dims) {
            $seg = $this->buildSegment($dims, $g->office_code, $g->akad_code, $g->usage_code);
            $g->group_key = $seg['group_key'];

            return $g;
        })->groupBy('group_key')->map(function ($subGroups, $groupKey) {
            $first = $subGroups->first();
            $first->total_ead = (float) $subGroups->sum('total_ead');
            $first->member_count = (int) $subGroups->sum('member_count');

            return $first;
        })->values();
    }

    private function individualRows(int $periodId): Collection
    {
        return DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->orderBy('ckpn_individual', 'desc')
            ->get();
    }

    private function collectiveRows(int $periodId): Collection
    {
        return DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->orderBy('ckpn_kolektif', 'desc')
            ->get();
    }

    private function summary(int $periodId): array
    {
        $individual = (float) DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('ckpn_individual');

        $collective = (float) DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('ckpn_kolektif');

        $totalEadInd = (float) DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('nilai_tercatat');

        $totalEadCol = (float) DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->sum('total_ead');

        return [
            'individual_ckpn' => round($individual, 2),
            'collective_ckpn' => round($collective, 2),
            'total_ckpn' => round($individual + $collective, 2),
            'total_ead' => round($totalEadInd + $totalEadCol, 2),
            'individual_ead' => round($totalEadInd, 2),
            'collective_ead' => round($totalEadCol, 2),
            'count_individual' => (int) DB::table('stage_individual_ckpn')
                ->where('assessment_period_id', $periodId)->count(),
            'count_collective' => (int) DB::table('stage_collective_ckpn')
                ->where('assessment_period_id', $periodId)->count(),
        ];
    }
}
