<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Sumber tunggal hasil perhitungan dari tabel stage_* (pasca konsolidasi pipeline multi-tahap).
 * Menggantikan pembacaan cache "calculation_results_*" dan tabel ckpn_results.
 */
final class CalculationResults
{
    public static function forPeriod(int $assessmentPeriodId): array
    {
        $collective = DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->get();

        $recordCount = (int) DB::table('stage_classifications')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->count();

        $totalEad = (float) DB::table('stage_classifications')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->sum('ead_amount');

        $collectiveEad = (float) $collective->sum('total_ead');

        $avgPd = $collectiveEad > 0.0
            ? (float) $collective->sum(fn ($g) => $g->pd * $g->total_ead) / $collectiveEad
            : (float) DB::table('stage_pd_results')
                ->where('assessment_period_id', $assessmentPeriodId)
                ->avg('pd_calculated');

        $avgLgd = $collectiveEad > 0.0
            ? (float) $collective->sum(fn ($g) => $g->lgd * $g->total_ead) / $collectiveEad
            : 0.0;

        $individualRows = DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->get()
            ->map(fn ($r) => [
                'loan_number' => $r->loan_number,
                'outstanding_balance' => (float) $r->nilai_tercatat,
                'principal_arrears' => 0.0,
                'estimated_recovery' => (float) $r->estimated_recovery,
                'ckpn_individual' => (float) $r->ckpn_individual,
                'recovery_method' => $r->recovery_method,
            ])->all();

        $totalIndividual = (float) DB::table('stage_individual_ckpn')
            ->where('assessment_period_id', $assessmentPeriodId)
            ->sum('ckpn_individual');

        $collectiveRows = $collective->map(fn ($g) => [
            'group_key' => $g->group_key,
            // Bucket debitur dalam grup (CKPN kolektif dipecah per segmen × bucket).
            'bucket_no' => (int) ($g->bucket_no ?? 1),
            'office_code' => $g->office_code,
            'akad_code' => $g->akad_code,
            'usage_code' => $g->usage_code,
            'ead' => (float) $g->total_ead,
            'pd' => (float) $g->pd,
            'lgd' => (float) $g->lgd,
            'member_count' => (int) $g->member_count,
            'ckpn' => (float) $g->ckpn_kolektif,
        ])->all();

        $totalCollective = (float) $collective->sum('ckpn_kolektif');

        return [
            'ead' => [
                'total_ead' => $totalEad,
                'record_count' => $recordCount,
                'total_count' => $recordCount,
            ],
            // Alias "pd"/"lgd" dipakai view report lama.
            'pd' => ['average_pd' => $avgPd],
            'lgd' => ['average_lgd' => $avgLgd],
            'pd_net_flow' => ['average_pd' => $avgPd],
            'lgd_weighted' => ['average_lgd' => $avgLgd],
            'ckpn_individual' => [
                'total_individual_ckpn' => $totalIndividual,
                // Alias: view report lama (risk-matrix, aging-analysis) + ReportingController pakai key ini.
                'total_ckpn_individual' => $totalIndividual,
                'individual_ckpn_results' => $individualRows,
            ],
            'ckpn_collective' => [
                'total_collective_ckpn' => $totalCollective,
                // Alias: view report lama + ReportingController pakai key ini.
                'total_ckpn_collective' => $totalCollective,
                'collective_ckpn_results' => $collectiveRows,
            ],
        ];
    }
}
