<?php

namespace App\Http\Controllers\Calculation;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Tampilan hasil CKPN Individual & Kolektif pasca finalisasi.
 * Sumber: stage_individual_ckpn / stage_collective_ckpn (scoped per periode).
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
                'count'          => $rows->count(),
                'total_tercatat' => (float) $rows->sum('nilai_tercatat'),
                'total_recovery' => (float) $rows->sum('estimated_recovery'),
                'total_ckpn'     => (float) $rows->sum('ckpn_individual'),
            ],
        ]);
    }

    public function collective(Request $request): View
    {
        $period  = $this->resolvePeriod($request);
        $periods = $this->periods();

        $rows = Schema::hasTable('stage_collective_ckpn')
            ? DB::table('stage_collective_ckpn')
                ->where('assessment_period_id', $period->id)
                ->orderByDesc('ckpn_kolektif')
                ->get()
            : collect();

        return view('calculation.modules.ckpn_collective', [
            'period'  => $period,
            'periods' => $periods,
            'rows'    => $rows,
            'summary' => [
                'count'      => $rows->count(),
                'total_ead'  => (float) $rows->sum('total_ead'),
                'total_ckpn' => (float) $rows->sum('ckpn_kolektif'),
                'members'    => (int) $rows->sum('member_count'),
            ],
        ]);
    }
}
