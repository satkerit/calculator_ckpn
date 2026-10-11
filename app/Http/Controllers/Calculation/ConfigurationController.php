<?php

namespace App\Http\Controllers\Calculation;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Modul 1: Konfigurasi CKPN.
 *
 * Mengelola `stage_configs` per periode: pd_method, lgd_method, historical
 * window, threshold, segmentasi.
 */
class ConfigurationController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $config = $this->configFor($period->id);
        $periods = $this->periods();

        return view('calculation.modules.configuration', compact('period', 'config', 'periods'));
    }

    public function save(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'assessment_period_id' => 'required|exists:assessment_periods,id',
            'pd_method' => 'required|in:NET_FLOW,MIGRATION',
            'lgd_method' => 'required|in:EXPECTED_RECOVERY,COLLATERAL_SHORTFALL,WEIGHTED',
            'historical_months' => 'required|integer|min:12|max:120',
            'lgd_historical_months' => 'required|integer|min:12|max:120',
            'sale_cost_percentage' => 'required|numeric|min:0|max:1',
            'segment_dimensions' => 'required|array|min:1',
            'segment_dimensions.*' => 'in:OFFICE,AKAD,USAGE',
            'individual_top_n' => 'required|integer|min:0|max:10000',
            'npl_ratings' => 'required|array|min:1',
            'npl_ratings.*' => 'integer|min:1|max:5',
        ], [
            'segment_dimensions.required' => 'Pilih minimal satu dimensi segmentasi.',
            'segment_dimensions.min' => 'Pilih minimal satu dimensi segmentasi.',
            'npl_ratings.required' => 'Pilih minimal satu kualitas (kolektibilitas) NPL.',
            'npl_ratings.min' => 'Pilih minimal satu kualitas (kolektibilitas) NPL.',
            'historical_months.min' => 'Historical window minimal 12 bulan.',
            'historical_months.max' => 'Historical window maksimal 120 bulan.',
            'sale_cost_percentage.max' => 'Sale cost harus berupa rasio 0–1 (mis. 0.06 untuk 6%).',
        ], [
            'pd_method' => 'Metode PD',
            'lgd_method' => 'Metode LGD',
            'historical_months' => 'Historical window',
            'lgd_historical_months' => 'Historical window LGD',
            'sale_cost_percentage' => 'Sale cost',
            'segment_dimensions' => 'Segmentasi',
            'individual_top_n' => 'TOP N Outstanding',
            'npl_ratings' => 'Kualitas NPL',
        ]);

        $this->saveConfig(
            (int) $validated['assessment_period_id'],
            [
                'pd_method' => $validated['pd_method'],
                'lgd_method' => $validated['lgd_method'],
                'historical_months' => (int) $validated['historical_months'],
                'lgd_historical_months' => (int) $validated['lgd_historical_months'],
                'sale_cost_percentage' => (float) $validated['sale_cost_percentage'],
                'segment_dimensions' => implode('|', $validated['segment_dimensions']),
                'individual_top_n' => (int) $validated['individual_top_n'],
                'npl_ratings' => implode(',', $validated['npl_ratings']),
            ]
        );

        // Konfigurasi berubah → hasil kalkulasi lama (PD/LGD/CKPN) yang memakai
        // konfigurasi sebelumnya otomatis TIDAK VALID (dibandingkan via fingerprint).
        // Hitung ulang modul terkait sebelum Finalize.
        $periodId = (int) $validated['assessment_period_id'];
        $stale = $this->staleResultTypes($periodId);
        $triggered = [];
        if ($stale !== []) {
            foreach ($stale as $type) {
                $triggered[] = $this->staleResultLabel($type);
            }
        }

        $this->audit('CONFIG', 'SAVE', $periodId, [
            'pd_method' => $validated['pd_method'],
            'lgd_method' => $validated['lgd_method'],
            'segment_dimensions' => implode('|', $validated['segment_dimensions']),
            'individual_top_n' => (int) $validated['individual_top_n'],
            'npl_ratings' => implode(',', $validated['npl_ratings']),
            'stale_results' => $stale,
        ]);

        $result = ['config_saved' => 1, 'pd' => $validated['pd_method'], 'lgd' => $validated['lgd_method']];
        if ($triggered !== []) {
            $result['recalc_required'] = implode(', ', $triggered).' — hasil lama tidak valid, wajib dihitung ulang';
            $this->audit('CONFIG', 'INVALIDATE_RESULTS', $periodId, [
                'stale_results' => $stale,
                'note' => 'Konfigurasi berubah; hasil kalkulasi terdahulu ditandai tidak valid.',
            ]);
        }

        return $this->backTo($request, 'calculation.module.config', $result);
    }
}
