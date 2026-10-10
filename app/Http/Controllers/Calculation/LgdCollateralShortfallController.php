<?php

namespace App\Http\Controllers\Calculation;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul 6: LGD Collateral Shortfall.
 *
 * Hitung shortfall agunan write-off per grup segmentasi.
 * LGD_CS = total_shortfall / total_exposure_wo.
 */
class LgdCollateralShortfallController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period  = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config  = $this->configFor($period->id);
        $rows    = $this->loadResults($period->id);

        return view('calculation.modules.lgd_cs', compact('period', 'periods', 'config', 'rows'));
    }

    public function run(Request $request): RedirectResponse
    {
        $period = $this->resolvePeriod($request);
        $config   = $this->configFor($period->id);
        $dims     = $this->segmentDimensions($request, $period->id);
        // Kertas kerja: window LGD 5 tahun (60 bulan) dari data snapshot.
        $histM    = (int) ($config['lgd_historical_months'] ?? 60);
        $saleCost = (float) $config['sale_cost_percentage'];
        $assessmentDate = $period->assessment_date;
        $startPeriod = $assessmentDate->copy()->subMonths($histM)->startOfMonth();

        DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', 'CS')
            ->delete();

        $groups = $this->loadCollectiveGroups($period->id, $dims);
        if ($groups->isEmpty()) {
            return $this->backTo($request, 'calculation.module.lgd_cs', [], 'Belum ada klasifikasi COLLECTIVE.');
        }

        // Ambil write-off di window, join dengan agunan untuk net recovery.
        $writeOffs = DB::table('write_offs as wo')
            ->leftJoin('collateral as c', 'c.loan_number', '=', 'wo.financing_id')
            ->whereBetween('wo.writeoff_date', [$startPeriod->toDateString(), $assessmentDate->toDateString()])
            ->selectRaw('wo.financing_id, wo.amount as wo_amount, COALESCE(SUM(c.liquidity_value), 0) as liq_value, COALESCE(SUM(c.estimated_value), 0) as est_value')
            ->groupBy('wo.id', 'wo.financing_id', 'wo.amount')
            ->get();

        // Map loan → segment (office_code/akad_code/usage_code)
        // Prioritas stage_classifications (aktif), fallback ke snapshot historis untuk loan non-aktif/write-off.
        $loanToSegment = DB::table('stage_classifications')
            ->where('assessment_period_id', $period->id)
            ->where('classification', 'COLLECTIVE')
            ->select('loan_number', 'office_code', 'akad_code', 'usage_code')
            ->get()
            ->keyBy('loan_number');

        $missingWoLoans = $writeOffs->pluck('financing_id')->filter(fn ($id) => ! $loanToSegment->has($id))->unique();
        if ($missingWoLoans->isNotEmpty()) {
            $fallbackSnaps = DB::table('snapshot')
                ->whereIn('loan_number', $missingWoLoans)
                ->select('loan_number', 'office_code', 'product_code', 'usage_type', 'financing_contract_code')
                ->get()
                ->groupBy('loan_number')
                ->map(function ($rows) {
                    $first = $rows->first();
                    return (object) [
                        'loan_number' => $first->loan_number,
                        'office_code' => $first->office_code ?? 'NA',
                        'akad_code'   => \App\Services\ProductAkadMappingService::resolveAkadCode($first->product_code ?? ''),
                        'usage_code'  => $first->usage_type ?? $first->financing_contract_code ?? 'OTHER',
                    ];
                });

            foreach ($fallbackSnaps as $ln => $segObj) {
                $loanToSegment->put($ln, $segObj);
            }
        }

        $byGroup = [];
        foreach ($writeOffs as $wo) {
            $seg = $loanToSegment->get($wo->financing_id);
            if (! $seg) {
                continue;
            }
            $key = $this->collectiveGroupKey($dims, $seg->office_code, $seg->akad_code, $seg->usage_code);

            $exposure  = (float) $wo->wo_amount;
            $netRecov  = max(0.0, (float) $wo->liq_value * (1 - $saleCost));
            $shortfall = max(0.0, $exposure - $netRecov);

            $byGroup[$key]['exposure']  = ($byGroup[$key]['exposure']  ?? 0.0) + $exposure;
            $byGroup[$key]['shortfall'] = ($byGroup[$key]['shortfall'] ?? 0.0) + $shortfall;
            $byGroup[$key]['net']       = ($byGroup[$key]['net']       ?? 0.0) + $netRecov;
            $byGroup[$key]['count']     = ($byGroup[$key]['count']     ?? 0) + 1;
        }

        $now   = now();
        $batch = [];

        foreach ($groups as $g) {
            $key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);
            $agg = $byGroup[$key] ?? ['exposure' => 0.0, 'shortfall' => 0.0, 'net' => 0.0, 'count' => 0];
            $lgd = $agg['exposure'] > 0
                ? min(1.0, max(0.0, $agg['shortfall'] / $agg['exposure']))
                : 1.0;

            $batch[] = [
                'assessment_period_id' => $period->id,
                'group_key'            => $key,
                'combo_segmentation_key' => implode('|', $dims),
                'office_code'          => $g->office_code,
                'akad_code'            => $g->akad_code,
                'usage_code'           => $g->usage_code,
                'method'               => 'CS',
                'total_exposure_wo'    => round($agg['exposure'], 2),
                'total_shortfall'      => round($agg['shortfall'], 2),
                'collateral_coverage'  => $agg['exposure'] > 0 ? round($agg['net'] / $agg['exposure'], 6) : 0.0,
                'account_count'        => $agg['count'],
                'lgd_calculated'       => round($lgd, 6),
                'detail'               => json_encode([
                    'method'        => 'CS',
                    'window_months' => $histM,
                    'sale_cost'     => $saleCost,
                ]),
                'created_at'           => $now,
                'updated_at'           => $now,
            ];
        }

        foreach (array_chunk($batch, 200) as $chunk) {
            $this->safeInsert('stage_lgd_results', $chunk);
        }

        $this->audit('LGD_CS', 'RUN', $period->id, [
            'groups' => $groups->count(),
            'window' => $histM,
            'inserted' => count($batch),
        ]);

        return $this->backTo($request, 'calculation.module.lgd_cs', [
            'groups' => $groups->count(),
            'method' => 'CS',
            'window' => $histM . ' bln',
        ]);
    }

    private function loadResults(int $periodId): array
    {
        $rows = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'CS')
            ->orderBy('lgd_calculated', 'desc')
            ->get();

        $avg = $rows->isEmpty() ? 0.0 : (float) $rows->avg('lgd_calculated');

        return [
            'rows'    => $rows,
            'count'   => $rows->count(),
            'avg_lgd' => round($avg, 6),
            'method'  => 'CS',
        ];
    }
}