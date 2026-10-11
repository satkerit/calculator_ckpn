<?php

namespace App\Http\Controllers\Calculation;

use App\Services\ProductAkadMappingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul 5: LGD Expected Recovery.
 *
 * Hitung recovery rate historis write-off per grup segmentasi.
 * LGD_ER = 1 - average_recovery_rate.
 */
class LgdExpectedRecoveryController extends BaseCalculationController
{
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config = $this->configFor($period->id);
        $rows = $this->loadResults($period->id);
        // Hasil basi bila dimensi segmentasi berubah setelah run terakhir (group_key LGD).
        $lgdStale = in_array('LGD', $this->staleResultTypes($period->id), true);

        return view('calculation.modules.lgd_er', compact('period', 'periods', 'config', 'rows', 'lgdStale'));
    }

    public function run(Request $request): RedirectResponse|JsonResponse
    {
        $period = $this->resolvePeriod($request);

        // Lock per-periode + transaksi agar penulisan hasil atomik & tidak bentrok antar user.
        return $this->withRunLock($period->id, fn () => DB::transaction(function () use ($request, $period) {
            $config = $this->configFor($period->id);
            $dims = $this->segmentDimensions($request, $period->id);
            // Kertas kerja: window LGD 5 tahun (60 bulan) dari data snapshot.
            $histM = (int) ($config['lgd_historical_months'] ?? 60);
            $assessmentDate = $period->assessment_date;

            // Bersihkan LGD ER untuk periode ini (aman: ter-scope ke period_id + ER).
            DB::table('stage_lgd_results')
                ->where('assessment_period_id', $period->id)
                ->where('method', 'ER')
                ->delete();

            $token = $this->progressToken($request);
            $this->pushProgress($token, 'Memuat data write-off historis…', 5);

            $groups = $this->loadCollectiveGroups($period->id, $dims);
            if ($groups->isEmpty()) {
                return $this->backTo($request, 'calculation.module.lgd_er', [], 'Belum ada klasifikasi COLLECTIVE.');
            }

            // Window write-off: (N-60)..N, dihitung dari periode snapshot (yyyymm).
            $assessYm = $assessmentDate->format('Ym');
            $startYm = $assessmentDate->copy()->subMonths($histM)->format('Ym');

            // Kertas kerja: hanya write-off dengan tglwo di dalam rentang 5 tahun.
            $windowLoans = DB::table('snapshot')
                ->where('write_off_flag', 1)
                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                ->whereRaw('SUBSTRING(tglwo, 1, 6) >= ?', [$startYm])
                ->whereRaw('SUBSTRING(tglwo, 1, 6) <= ?', [$assessYm])
                ->distinct()
                ->pluck('loan_number');

            $this->pushProgress($token, 'Memuat data write-off historis…', 20);

            $byGroup = [];
            $fallbackCount = 0; // jumlah grup yang memakai LGD fallback (tanpa data write-off)

            if ($windowLoans->isNotEmpty()) {
                // Outstanding "terakhir pada saat write-off dijalankan" = snapshot WO dengan periode terbesar.
                $woBalances = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('write_off_flag', 1)
                    ->orderBy('periode_snapshot')
                    ->get(['loan_number', 'periode_snapshot', 'outstanding_balance', 'office_code', 'product_code', 'usage_type', 'financing_contract_code'])
                    ->groupBy('loan_number')
                    ->map(fn ($rows) => $rows->last());

                // Posisi outstanding pada periode assessment terakhir.
                $lastBalances = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('periode_snapshot', $assessYm)
                    ->pluck('outstanding_balance', 'loan_number');

                foreach ($woBalances as $loanNumber => $row) {
                    $woNominal = (float) $row->outstanding_balance;
                    // Tidak ditemukan pada periode terakhir → debitur lunas dari write-off → recovery 100%.
                    $lastPos = isset($lastBalances[$loanNumber]) ? (float) $lastBalances[$loanNumber] : 0.0;
                    $recovery = max(0.0, $woNominal - $lastPos);

                    $akadCode = ProductAkadMappingService::resolveAkadCode($row->product_code ?? '');
                    $usageCode = $row->usage_type ?? $row->financing_contract_code ?? 'OTHER';
                    $key = $this->collectiveGroupKey($dims, $row->office_code, $akadCode, $usageCode);

                    $byGroup[$key]['wo'] = ($byGroup[$key]['wo'] ?? 0.0) + $woNominal;
                    $byGroup[$key]['recovery'] = ($byGroup[$key]['recovery'] ?? 0.0) + $recovery;
                    $byGroup[$key]['count'] = ($byGroup[$key]['count'] ?? 0) + 1;
                }
            }

            $now = now();
            $batch = [];

            foreach ($groups as $g) {
                $key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);
                $agg = $byGroup[$key] ?? ['wo' => 0.0, 'recovery' => 0.0, 'count' => 0];
                $recoveryRate = $agg['wo'] > 0
                    ? min(1.0, max(0.0, $agg['recovery'] / $agg['wo']))
                    : 0.0;
                $lgd = min(1.0, max(0.0, 1.0 - $recoveryRate));

                // Fallback LGD 50% (sesuai spec) bila grup tidak punya write-off historis sama sekali.
                $fallbackUsed = $agg['wo'] == 0.0;
                if ($fallbackUsed) {
                    $lgd = 0.5;
                    $fallbackCount++;
                }

                $batch[] = [
                    'assessment_period_id' => $period->id,
                    'group_key' => $key,
                    'combo_segmentation_key' => implode('|', $dims),
                    'office_code' => $g->office_code,
                    'akad_code' => $g->akad_code,
                    'usage_code' => $g->usage_code,
                    'method' => 'ER',
                    'recovery_rate' => round($recoveryRate, 6),
                    'total_write_off' => round($agg['wo'], 2),
                    'total_recovery' => round($agg['recovery'], 2),
                    'years_analyzed' => (int) ceil($histM / 12),
                    'lgd_calculated' => round($lgd, 6),
                    'detail' => json_encode([
                        'method' => 'ER',
                        'window_months' => $histM,
                        'window_start' => $startYm,
                        'window_end' => $assessYm,
                        'wo_accounts' => $agg['count'],
                        'fallback_used' => $fallbackUsed,
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            $this->pushProgress($token, 'Menyimpan hasil LGD Expected Recovery…', 95);
            foreach (array_chunk($batch, 200) as $chunk) {
                $this->safeInsert('stage_lgd_results', $chunk);
            }

            // Tandai hasil LGD sah dengan dimensi segmentasi yang dipakai saat run (fingerprint).
            $this->markResults($period->id, 'LGD', ['segment_dimensions' => $dims]);

            $this->audit('LGD_ER', 'RUN', $period->id, [
                'groups' => $groups->count(),
                'window' => $histM,
                'inserted' => count($batch),
                'fallback_groups' => $fallbackCount,
            ]);

            return $this->backTo($request, 'calculation.module.lgd_er', [
                'groups' => $groups->count(),
                'method' => 'ER',
                'window' => $histM.' bln',
            ]);
        }));
    }

    private function loadResults(int $periodId): array
    {
        $rows = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'ER')
            ->orderBy('office_code')
            ->orderBy('akad_code')
            ->orderBy('usage_code')
            ->get();

        $avg = $rows->isEmpty() ? 0.0 : (float) $rows->avg('lgd_calculated');

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'avg_lgd' => round($avg, 6),
            'method' => 'ER',
        ];
    }
}
