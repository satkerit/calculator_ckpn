<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Collateral;
use App\Services\Ckpn\CkpnCalculator;
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
 * - Kolektif  : EAD × PD × LGD (LGD = weighted ER + CS, fallback ke lgd_default)
 */
class DetailCkpnController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period  = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config  = $this->configFor($period->id);

        $individuals = $this->individualRows($period->id);
        $collectives = $this->collectiveRows($period->id);
        $summary     = $this->summary($period->id);

        return view('calculation.modules.detail_ckpn', compact(
            'period',
            'periods',
            'config',
            'individuals',
            'collectives',
            'summary',
        ));
    }

    public function finalize(Request $request): RedirectResponse
    {
        $period = $this->resolvePeriod($request);
        $config = $this->configFor($period->id);

        // Hitung ulang individual + collective untuk konsistensi.
        $this->computeIndividual($period->id, $config);
        $this->computeCollective($period->id, $config);

        $summary = $this->summary($period->id);

        $this->audit('CKPN', 'FINALIZE', $period->id, $summary);

        return $this->backTo($request, 'calculation.module.detail_ckpn', [
            'finalized' => 1,
            'individual_count' => $summary['count_individual'],
            'collective_count' => $summary['count_collective'],
            'total_ckpn' => $summary['total_ckpn'],
        ]);
    }

    private function computeIndividual(int $periodId, array $config): void
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

        $collateralMap = Collateral::all()
            ->groupBy('loan_number')
            ->map(fn ($rows) => (float) $rows->sum('liquidity_value'))
            ->toArray();

        $now   = now();
        $batch = [];

        foreach ($individuals as $ind) {
            $loanNumber = $ind->loan_number;
            $liquidity  = (float) ($collateralMap[$loanNumber] ?? 0);
            $nilai      = (float) $ind->ead_amount;

            $ckpn = CkpnCalculator::calculateIndividual($nilai, $liquidity, $saleCostPct);

            $batch[] = [
                'assessment_period_id' => $periodId,
                'loan_number'          => $loanNumber,
                'nilai_tercatat'       => $nilai,
                'estimated_recovery'   => round($nilai - $ckpn, 2),
                'ckpn_individual'      => $ckpn,
                'recovery_method'      => $liquidity > 0 ? 'COLLATERAL_LIQUIDITY' : 'NO_COLLATERAL',
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_individual_ckpn', $chunk);
        }
    }

    private function computeCollective(int $periodId, array $config): void
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

        // PD per grup.
        $pdMap = DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->get()
            ->keyBy('group_key');

        // LGD per grup (prefer WEIGHTED = avg(ER, CS)).
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

        $lgdDefault = 1.0;
        $now   = now();
        $batch = [];

        foreach ($eadGroups as $eg) {
            $key     = $eg->group_key;
            $pd      = isset($pdMap[$key]) ? (float) $pdMap[$key]->pd_calculated : 0.0;
            $erObj = $lgdEr[$key] ?? null;
            $csObj = $lgdCs[$key] ?? null;

            $erVal = $erObj ? (float) $erObj->lgd_calculated : null;
            $csVal = $csObj ? (float) $csObj->lgd_calculated : null;

            // Kertas kerja (B4.LGD-Collateral Shortfall):
            // Weighted LGD = Total Loss / Total Exposure = (Loss_ER + Loss_CS) / (Wo_ER + Exposure_CS)
            $woEr    = $erObj ? (float) $erObj->total_write_off : 0.0;
            $lossEr  = $erObj ? ($woEr - (float) $erObj->total_recovery) : 0.0;

            $expCs   = $csObj ? (float) $csObj->total_exposure_wo : 0.0;
            $lossCs  = $csObj ? (float) $csObj->total_shortfall : 0.0;

            $totalExp  = $woEr + $expCs;
            $totalLoss = max(0.0, $lossEr) + max(0.0, $lossCs);

            $lgd = match (true) {
                $totalExp > 0                      => $totalLoss / $totalExp,
                $erVal !== null && $csVal !== null => ($erVal + $csVal) / 2,
                $erVal !== null                    => $erVal,
                $csVal !== null                    => $csVal,
                default                            => $lgdDefault,
            };
            $lgd = min(1.0, max(0.0, $lgd));
            $totalEad = (float) $eg->total_ead;

            $ckpn = CkpnCalculator::calculateCollective($pd, $lgd, $totalEad);

            $batch[] = [
                'assessment_period_id' => $periodId,
                'group_key'            => $key,
                'office_code'          => $eg->office_code,
                'akad_code'            => $eg->akad_code,
                'usage_code'           => $eg->usage_code,
                'total_ead'            => round($totalEad, 2),
                'pd'                   => round($pd, 6),
                'lgd'                  => round($lgd, 6),
                'lgd_method'           => 'WEIGHTED',
                'ckpn_kolektif'        => $ckpn,
                'member_count'         => (int) $eg->member_count,
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_collective_ckpn', $chunk);
        }
    }

    /**
     * EAD totals per grup segmentasi: prioritas stage_ead_buckets, fallback ke
     * stage_classifications (agar CKPN kolektif tetap terhitung walau modul
     * EAD bucket belum dijalankan).
     */
    private function eadGroups(int $periodId): Collection
    {
        if (Schema::hasTable('stage_ead_buckets')) {
            $fromBuckets = DB::table('stage_ead_buckets')
                ->where('assessment_period_id', $periodId)
                ->selectRaw('group_key, MAX(office_code) as office_code, MAX(akad_code) as akad_code, MAX(usage_code) as usage_code, SUM(ead) as total_ead, SUM(member_count) as member_count')
                ->groupBy('group_key')
                ->get();

            if ($fromBuckets->isNotEmpty()) {
                return $fromBuckets;
            }
        }

        $dims = $this->segmentDimensions(request(), $periodId);
        $rows = DB::table('stage_classifications')
            ->where('assessment_period_id', $periodId)
            ->where('classification', 'COLLECTIVE')
            ->selectRaw("office_code, akad_code, usage_code, SUM(ead_amount) as total_ead, COUNT(*) as member_count")
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

    private function individualRows(int $periodId): \Illuminate\Support\Collection
    {
        return DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $periodId)
            ->orderBy('ckpn_individual', 'desc')
            ->get();
    }

    private function collectiveRows(int $periodId): \Illuminate\Support\Collection
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
            'individual_ckpn'    => round($individual, 2),
            'collective_ckpn'    => round($collective, 2),
            'total_ckpn'         => round($individual + $collective, 2),
            'total_ead'          => round($totalEadInd + $totalEadCol, 2),
            'individual_ead'     => round($totalEadInd, 2),
            'collective_ead'     => round($totalEadCol, 2),
            'count_individual'   => (int) DB::table('stage_individual_ckpn')
                ->where('assessment_period_id', $periodId)->count(),
            'count_collective'   => (int) DB::table('stage_collective_ckpn')
                ->where('assessment_period_id', $periodId)->count(),
        ];
    }
}