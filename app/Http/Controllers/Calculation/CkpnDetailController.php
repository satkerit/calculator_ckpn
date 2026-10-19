<?php

namespace App\Http\Controllers\Calculation;

use App\Services\Bucket\BucketAssignmentService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Tampilan daftar debitur keseluruhan hasil CKPN (per debitur, bukan per grup).
 * Sumber:
 * - Individual: stage_individual_ckpn (EAD + agunan + pengurangan).
 * - Kolektif  : stage_classifications × snapshot (bucket) × stage_collective_ckpn (PD/LGD per grup×bucket).
 */
class CkpnDetailController extends BaseCalculationController
{
    public function individual(Request $request): View
    {
        $period  = $this->resolvePeriod($request);
        $periods = $this->periods();

        $rows = Schema::hasTable('stage_individual_ckpn')
            ? DB::table('stage_individual_ckpn')
                ->where('assessment_period_id', $period->id)
                ->orderByDesc('ckpn_individual')
                ->get()
            : collect();

        return view('calculation.modules.ckpn_individual', [
            'period'  => $period,
            'periods' => $periods,
            'rows'    => $rows,
            'summary' => [
                'count'             => $rows->count(),
                'total_ead'         => (float) $rows->sum('nilai_tercatat'),
                'total_agunan'      => (float) $rows->sum('liquidity_value'),
                'total_pengurangan' => (float) $rows->sum('sale_cost'),
                'total_recovery'    => (float) $rows->sum('estimated_recovery'),
                'total_ckpn'        => (float) $rows->sum('ckpn_individual'),
            ],
        ]);
    }

    public function collective(Request $request): View
    {
        $period  = $this->resolvePeriod($request);
        $periods = $this->periods();

        // Daftar debitur kolektif: EAD per rekening + PD/LGD per grup×bucket
        // (diambil dari hasil CKPN kolektif yang sudah tersimpan agar konsisten).
        $rows = Schema::hasTable('stage_classifications') && Schema::hasTable('stage_collective_ckpn')
            ? $this->collectiveDebtorRows($request, $period->id)
            : collect();

        return view('calculation.modules.ckpn_collective', [
            'period'  => $period,
            'periods' => $periods,
            'rows'    => $rows,
            'summary' => [
                'count'      => $rows->count(),
                'total_ead'  => (float) $rows->sum('ead_amount'),
                'total_ckpn' => (float) $rows->sum('ckpn_debitur'),
                'groups'     => $rows->pluck('group_key')->unique()->count(),
            ],
        ]);
    }

    /**
     * Baris per debitur kolektif: loan_number + segmen + dimensi risiko + EAD + PD + LGD + CKPN.
     * Dimensi risiko mengikuti metode PD aktif:
     * - NET_FLOW : bucket_no (1..14)
     * - MIGRATION: quality_rating (1..5)
     */
    private function collectiveDebtorRows(Request $request, int $periodId)
    {
        $dims = $this->segmentDimensions($request, $periodId);
        $periodeYm = $this->assessmentYm($periodId);

        $cfgRow = DB::table('stage_configs')->where('assessment_period_id', $periodId)->first();
        $isMigration = strtoupper((string) ($cfgRow->pd_method ?? 'NET_FLOW')) === 'MIGRATION';

        $rows = DB::table('stage_classifications as sc')
            ->leftJoin('snapshot as s', function ($j) use ($periodeYm) {
                $j->on('sc.loan_number', '=', 's.loan_number')
                    ->where('s.periode_snapshot', '=', $periodeYm)
                    ->where('s.active_flag', '=', true);
            })
            ->where('sc.assessment_period_id', $periodId)
            ->where('sc.classification', 'COLLECTIVE')
            ->select(
                'sc.loan_number',
                'sc.office_code',
                'sc.akad_code',
                'sc.usage_code',
                'sc.ead_amount',
                DB::raw('COALESCE(s.bucket_no, 1) as bucket_no'),
                DB::raw('CAST(COALESCE(s.quality_rating, 1) AS UNSIGNED) as quality_rating'),
            )
            ->get();

        if ($rows->isEmpty()) {
            return $rows;
        }

        // PD/LGD per grup × dimensi risiko dari hasil CKPN kolektif tersimpan.
        $ckpnMap = DB::table('stage_collective_ckpn')
            ->where('assessment_period_id', $periodId)
            ->get()
            ->keyBy(fn ($r) => $r->group_key.'|'.(int) $r->bucket_no);

        return $rows->map(function ($r) use ($dims, $ckpnMap, $isMigration) {
            $r->group_key = $this->collectiveGroupKey($dims, $r->office_code, $r->akad_code, $r->usage_code);
            $r->bucket_no = (int) $r->bucket_no;
            $r->quality_rating = (int) $r->quality_rating;
            $r->bucket_label = BucketAssignmentService::getBucketLabel($r->bucket_no);

            // MIGRATION: PD tersimpan per quality_rating (bucket_no = rating).
            $dimKey = $isMigration ? $r->quality_rating : $r->bucket_no;
            $g = $ckpnMap[$r->group_key.'|'.$dimKey] ?? null;
            $r->dim_no = $dimKey;
            $r->dim_label = $isMigration ? 'Rating '.$r->quality_rating : $r->bucket_label;
            $r->pd = $g ? (float) $g->pd : null;
            $r->lgd = $g ? (float) $g->lgd : null;
            $r->lgd_method = $g->lgd_method ?? null;
            $r->ckpn_debitur = $g
                ? round((float) $r->ead_amount * (float) $g->pd * (float) $g->lgd, 2)
                : 0.0;

            return $r;
        })->sortByDesc('ckpn_debitur')->values();
    }

    /**
     * Periode snapshot (Ym) dari assessment_date periode — konsisten dengan
     * parsing yang dipakai ClassificationController & DetailCkpnController.
     */
    private function assessmentYm(int $periodId): string
    {
        $assessmentDate = DB::table('assessment_periods')->where('id', $periodId)->value('assessment_date');

        return $assessmentDate ? Carbon::parse($assessmentDate)->format('Ym') : '999912';
    }
}
