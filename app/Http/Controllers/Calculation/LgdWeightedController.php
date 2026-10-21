<?php

namespace App\Http\Controllers\Calculation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * LGD Weighted Controller
 *
 * Menggabungkan LGD ER + LGD CS per segmentasi (group_key)
 *
 * Rumus:
 * - Total WO = WO ER + Baki Debet CS
 * - Total LGD = (WO ER - Recovery ER) + Shortfall CS
 * - LGD Weighted Rate = Total LGD / Total WO
 */
class LgdWeightedController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $periodId = $period->id;

        // Load hasil LGD ER per group
        $erResults = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'ER')
            ->get();

        // Load hasil LGD CS per group
        $csResults = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'CS')
            ->get();

        // Group by group_key
        $erByGroup = $erResults->keyBy('group_key');
        $csByGroup = $csResults->keyBy('group_key');
        $allGroups = $erByGroup->keys()->merge($csByGroup->keys())->unique()->sort()->values();

        // Hitung weighted per group
        $groupSummary = $allGroups->map(function ($groupKey) use ($erByGroup, $csByGroup) {
            $er = $erByGroup->get($groupKey);
            $cs = $csByGroup->get($groupKey);

            // ER menyimpan eksposur di total_write_off & total_recovery (bukan
            // total_exposure_wo/total_shortfall yang dipakai method CS).
            $woER = $er ? (float) ($er->total_write_off ?? $er->total_exposure_wo ?? 0.0) : 0.0;
            $recoveryER = $er
                ? (float) ($er->total_recovery ?? max(0.0, $woER - (float) ($er->total_shortfall ?? 0.0)))
                : 0.0;
            $lossER = max(0.0, $woER - $recoveryER);

            $bakiCS = $cs ? (float) ($cs->total_exposure_wo ?? 0.0) : 0.0;
            $shortfallCS = $cs ? (float) ($cs->total_shortfall ?? 0.0) : 0.0;

            $totalWO = $woER + $bakiCS;
            $totalLGD = $lossER + $shortfallCS; // = (woER - recoveryER) + shortfallCS
            $lgdRate = $totalWO > 0 ? ($totalLGD / $totalWO) : 0.0;

            return (object) [
                'group_key' => $groupKey,
                'wo_er' => $woER,
                'recovery_er' => $recoveryER,
                'loss_er' => $lossER,
                'baki_cs' => $bakiCS,
                'shortfall_cs' => $shortfallCS,
                'total_wo' => $totalWO,
                'total_lgd' => $totalLGD,
                'lgd_weighted_rate' => $lgdRate,
            ];
        })->sortByDesc('total_wo')->values();

        // Total keseluruhan
        $totalWoER = $groupSummary->sum('wo_er');
        $totalRecoveryER = $groupSummary->sum('recovery_er');
        $totalLossER = $groupSummary->sum('loss_er');
        $totalBakiCS = $groupSummary->sum('baki_cs');
        $totalShortfallCS = $groupSummary->sum('shortfall_cs');
        $totalWO = $groupSummary->sum('total_wo');
        $totalLGD = $groupSummary->sum('total_lgd');
        $totalLgdRate = $totalWO > 0 ? ($totalLGD / $totalWO) : 0.0;

        $totalSummary = (object) [
            'wo_er' => $totalWoER,
            'recovery_er' => $totalRecoveryER,
            'loss_er' => $totalLossER,
            'baki_cs' => $totalBakiCS,
            'shortfall_cs' => $totalShortfallCS,
            'total_wo' => $totalWO,
            'total_lgd' => $totalLGD,
            'lgd_weighted_rate' => $totalLgdRate,
        ];

        return view('calculation.lgd_weighted', [
            'period' => $period,
            'periods' => $this->periods(),
            'groupSummary' => $groupSummary,
            'totalSummary' => $totalSummary,
            'emptyNotice' => $groupSummary->isEmpty() ? 'Belum ada perhitungan LGD ER/CS untuk periode ini.' : null,
        ]);
    }
}
