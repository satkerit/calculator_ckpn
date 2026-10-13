<?php

namespace App\Http\Controllers\Calculation;

use App\Services\ProductAkadMappingService;
use Illuminate\Http\JsonResponse;
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
        $period = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config = $this->configFor($period->id);
        $rows = $this->loadResults($period->id);
        // Hasil basi bila dimensi segmentasi berubah setelah run terakhir (group_key LGD).
        $lgdStale = in_array('LGD', $this->staleResultTypes($period->id), true);

        return view('calculation.modules.lgd_cs', compact('period', 'periods', 'config', 'rows', 'lgdStale'));
    }

    public function run(Request $request): RedirectResponse|JsonResponse
    {
        $period = $this->resolvePeriod($request);

        // Lock per-periode + transaksi agar penulisan hasil atomik & tidak bentrok antar user.
        return $this->withRunLock($period->id, fn () => DB::transaction(function () use ($request, $period) {
            $config = $this->configFor($period->id);
            $dims = $this->segmentDimensions($request, $period->id);
            // Kertas kerja CS: haircut/biaya penjualan atas nilai agunan.
            $saleCost = (float) $config['sale_cost_percentage'];
            $histM = (int) ($config['lgd_historical_months'] ?? 60);
            $assessmentDate = $period->assessment_date;

            DB::table('stage_lgd_results')
                ->where('assessment_period_id', $period->id)
                ->where('method', 'CS')
                ->delete();

            $token = $this->progressToken($request);
            $this->pushProgress($token, 'Memuat data write-off 5 tahun & agunan…', 5);

            $groups = $this->loadCollectiveGroups($period->id, $dims);
            if ($groups->isEmpty()) {
                return $this->backTo($request, 'calculation.module.lgd_cs', [], 'Belum ada klasifikasi COLLECTIVE.');
            }

            // Periode assessment (yyyymm) & window 5 tahun.
            $assessYm = $assessmentDate->format('Ym');
            $startYm = $assessmentDate->copy()->subMonths($histM)->format('Ym');

            // Populasi CS sesuai klarifikasi:
            //   (a) pembiayaan kualitas 5 (macet) YANG MASIH AKTIF pada periode assessment, ATAU
            //   (b) pembiayaan write-off AKTIF dengan tglwo dalam 5 tahun terakhir.
            // Keduanya WAJIB memiliki jaminan aktif bernilai (liquidity_value > 0).
            // Saling eksklusif dengan ER: WO tanpa jaminan bernilai masuk ER.
            // Baki debet = outstanding terakhir periode assessment (bukan saat WO).
            $csRows = DB::table('snapshot')
                ->where('periode_snapshot', $assessYm)
                ->where('active_flag', 1)
                ->where(function ($q) use ($startYm, $assessYm) {
                    // (a) Kualitas 5 aktif
                    $q->whereRaw('CAST(quality_rating AS UNSIGNED) = 5')
                        ->orWhere(function ($q2) use ($startYm, $assessYm) {
                            // (b) WO aktif dengan tglwo dalam 5 tahun terakhir
                            $q2->where('write_off_flag', 1)
                                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                                ->whereRaw('SUBSTRING(tglwo, 1, 6) >= ?', [$startYm])
                                ->whereRaw('SUBSTRING(tglwo, 1, 6) <= ?', [$assessYm]);
                        });
                })
                ->get(['loan_number', 'outstanding_balance', 'office_code', 'product_code', 'usage_type', 'financing_contract_code'])
                ->keyBy('loan_number');

            $this->pushProgress($token, 'Memuat baki debet & agunan periode assessment…', 20);

            $byGroup = [];

            if ($csRows->isNotEmpty()) {
                // Kode jenis agunan yang memenuhi syarat untuk LGD CS
                // (agunan yang dapat dieksekusi/dijual untuk menutup kerugian).
                $eligibleCollateralCodes = ['02','04','08','11','12','17','18','19','21','22','23','24','25','26','27','28','29'];

                // Nilai agunan (liquidity value) per loan — hanya jaminan aktif bernilai
                // DENGAN kode jenis agunan yang memenuhi syarat.
                $collateralByLoan = DB::table('collateral')
                    ->whereIn('loan_number', $csRows->keys())
                    ->where('active_flag', 1)
                    ->where('liquidity_value', '>', 0)
                    ->whereIn('collateral_type', $eligibleCollateralCodes)
                    ->selectRaw('loan_number, COALESCE(SUM(liquidity_value), 0) as liq_value')
                    ->groupBy('loan_number')
                    ->pluck('liq_value', 'loan_number');

                // Wajib berjamin bernilai & berkode eligible: hanya loan yang lolos yang dihitung.
                $csRows = $csRows->only($collateralByLoan->keys()->all());

                $totalWo = max(1, $csRows->count());
                $woIdx = 0;

                foreach ($csRows as $loanNumber => $row) {
                    $woIdx++;
                    if ($token !== null && ($woIdx % 50 === 0 || $woIdx === $totalWo)) {
                        $this->pushProgress($token, "Memproses baki debet & agunan rekening {$woIdx}/{$totalWo}…", 20 + ($woIdx / $totalWo) * 45, [
                            'current' => $woIdx, 'total' => $totalWo, 'label' => 'Proses Debitur CS',
                        ]);
                    }
                    // Baki debet = outstanding periode assessment terakhir.
                    $exposure = (float) $row->outstanding_balance;
                    $liqValue = (float) ($collateralByLoan[$loanNumber] ?? 0.0);
                    // Net recovery = nilai agunan − (nilai agunan × haircut/biaya penjualan).
                    $netRecov = max(0.0, $liqValue * (1 - $saleCost));
                    $shortfall = max(0.0, $exposure - $netRecov);

                    $akadCode = ProductAkadMappingService::resolveAkadCode($row->product_code ?? '');
                    $usageCode = $row->usage_type ?? $row->financing_contract_code ?? 'OTHER';
                    $key = $this->collectiveGroupKey($dims, $row->office_code, $akadCode, $usageCode);

                    $byGroup[$key]['exposure'] = ($byGroup[$key]['exposure'] ?? 0.0) + $exposure;
                    $byGroup[$key]['shortfall'] = ($byGroup[$key]['shortfall'] ?? 0.0) + $shortfall;
                    $byGroup[$key]['net'] = ($byGroup[$key]['net'] ?? 0.0) + $netRecov;
                    $byGroup[$key]['count'] = ($byGroup[$key]['count'] ?? 0) + 1;
                }
            }

            $now = now();
            $batch = [];
            $totalGroups = max(1, $groups->count());
            $gIdx = 0;

            // Portfolio-level LGD CS (PSAK 414): aggregate semua exposure & shortfall
            // dari seluruh populasi CS. Dipakai sebagai fallback untuk grup yang
            // tidak punya data historis (sparsity akibat segmentasi).
            $portfolioExp = 0.0;
            $portfolioShort = 0.0;
            foreach ($byGroup as $agg) {
                $portfolioExp += $agg['exposure'];
                $portfolioShort += $agg['shortfall'];
            }
            $portfolioLgdCs = $portfolioExp > 0
                ? min(1.0, max(0.0, $portfolioShort / $portfolioExp))
                : 1.0;

            foreach ($groups as $g) {
                $gIdx++;
                $key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);
                if ($token !== null && ($gIdx % 20 === 0 || $gIdx === $totalGroups)) {
                    $this->pushProgress($token, "Menghitung LGD Collateral Shortfall grup {$gIdx}/{$totalGroups} ({$key})…", 65 + ($gIdx / $totalGroups) * 27, [
                        'current' => $gIdx, 'total' => $totalGroups, 'label' => 'Hitung LGD CS',
                    ]);
                }
                $agg = $byGroup[$key] ?? ['exposure' => 0.0, 'shortfall' => 0.0, 'net' => 0.0, 'count' => 0];
                $fallbackUsed = $agg['exposure'] == 0.0;
                $lgd = $agg['exposure'] > 0
                    ? min(1.0, max(0.0, $agg['shortfall'] / $agg['exposure']))
                    : $portfolioLgdCs;

                $batch[] = [
                    'assessment_period_id' => $period->id,
                    'group_key' => $key,
                    'combo_segmentation_key' => implode('|', $dims),
                    'office_code' => $g->office_code,
                    'akad_code' => $g->akad_code,
                    'usage_code' => $g->usage_code,
                    'method' => 'CS',
                    'total_exposure_wo' => round($agg['exposure'], 2),
                    'total_shortfall' => round($agg['shortfall'], 2),
                    'collateral_coverage' => $agg['exposure'] > 0 ? round($agg['net'] / $agg['exposure'], 6) : 0.0,
                    'account_count' => $agg['count'],
                    'lgd_calculated' => round($lgd, 6),
                    'detail' => json_encode([
                        'method' => 'CS',
                        'population' => 'quality_rating=5 aktif OR WO aktif (5thn) dengan jaminan aktif bernilai & kode agunan eligible',
                        'eligible_collateral_codes' => $eligibleCollateralCodes,
                        'assessment_ym' => $assessYm,
                        'sale_cost' => $saleCost,
                        'fallback_used' => $fallbackUsed,
                        'fallback_lgd' => $fallbackUsed ? round($portfolioLgdCs, 6) : null,
                        'portfolio_exposure' => round($portfolioExp, 2),
                        'portfolio_shortfall' => round($portfolioShort, 2),
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $this->pushProgress($token, 'Menyimpan hasil LGD Collateral Shortfall…', 95);
            foreach (array_chunk($batch, 200) as $chunk) {
                $this->safeInsert('stage_lgd_results', $chunk);
            }

            // Tandai hasil LGD sah dengan dimensi segmentasi yang dipakai saat run (fingerprint).
            $this->markResults($period->id, 'LGD', ['segment_dimensions' => $dims]);

            $this->audit('LGD_CS', 'RUN', $period->id, [
                'groups' => $groups->count(),
                'population' => 'quality_rating=5 atau write-off, aktif',
                'inserted' => count($batch),
            ]);

            return $this->backTo($request, 'calculation.module.lgd_cs', [
                'groups' => $groups->count(),
                'method' => 'CS',
                'population' => 'Kualitas 5 / WO aktif',
            ]);
        }));
    }

    private function loadResults(int $periodId): array
    {
        $rows = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'CS')
            ->orderBy('office_code')
            ->orderBy('akad_code')
            ->orderBy('usage_code')
            ->get();

        $avg = $rows->isEmpty() ? 0.0 : (float) $rows->avg('lgd_calculated');

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'avg_lgd' => round($avg, 6),
            'method' => 'CS',
        ];
    }
}
