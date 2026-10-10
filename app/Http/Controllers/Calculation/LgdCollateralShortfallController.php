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

        // Window write-off: (N-60)..N, dihitung dari periode snapshot (yyyymm).
        $assessYm = $assessmentDate->format('Ym');
        $startYm  = $assessmentDate->copy()->subMonths($histM)->format('Ym');

        // Kertas kerja: write-off dengan tglwo di dalam rentang 5 tahun.
        // Exposure = outstanding terakhir saat WO; kolateral dijoin per loan untuk net recovery.
        $windowLoans = DB::table('snapshot')
            ->where('write_off_flag', 1)
            ->whereNotNull('tglwo')->where('tglwo', '<>', '')
            ->whereRaw('SUBSTRING(tglwo, 1, 6) >= ?', [$startYm])
            ->whereRaw('SUBSTRING(tglwo, 1, 6) <= ?', [$assessYm])
            ->distinct()
            ->pluck('loan_number');

        $byGroup = [];

        if ($windowLoans->isNotEmpty()) {
            // Outstanding terakhir saat write-off + atribut segmen, per loan.
            $woRows = DB::table('snapshot')
                ->whereIn('loan_number', $windowLoans)
                ->where('write_off_flag', 1)
                ->orderBy('periode_snapshot')
                ->get(['loan_number', 'outstanding_balance', 'office_code', 'product_code', 'usage_type', 'financing_contract_code'])
                ->groupBy('loan_number')
                ->map(fn ($rows) => $rows->last());

            // Nilai agunan (liquidity value) per loan.
            $collateralByLoan = DB::table('collateral')
                ->whereIn('loan_number', $windowLoans)
                ->selectRaw('loan_number, COALESCE(SUM(liquidity_value), 0) as liq_value')
                ->groupBy('loan_number')
                ->pluck('liq_value', 'loan_number');

            foreach ($woRows as $loanNumber => $row) {
                $exposure = (float) $row->outstanding_balance;
                $liqValue = (float) ($collateralByLoan[$loanNumber] ?? 0.0);
                $netRecov = max(0.0, $liqValue * (1 - $saleCost));
                $shortfall = max(0.0, $exposure - $netRecov);

                $akadCode  = \App\Services\ProductAkadMappingService::resolveAkadCode($row->product_code ?? '');
                $usageCode = $row->usage_type ?? $row->financing_contract_code ?? 'OTHER';
                $key = $this->collectiveGroupKey($dims, $row->office_code, $akadCode, $usageCode);

                $byGroup[$key]['exposure']  = ($byGroup[$key]['exposure']  ?? 0.0) + $exposure;
                $byGroup[$key]['shortfall'] = ($byGroup[$key]['shortfall'] ?? 0.0) + $shortfall;
                $byGroup[$key]['net']       = ($byGroup[$key]['net']       ?? 0.0) + $netRecov;
                $byGroup[$key]['count']     = ($byGroup[$key]['count']     ?? 0) + 1;
            }
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
                    'window_start'  => $startYm,
                    'window_end'    => $assessYm,
                    'sale_cost'     => $saleCost,
                    'fallback_used' => $agg['exposure'] == 0.0,
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