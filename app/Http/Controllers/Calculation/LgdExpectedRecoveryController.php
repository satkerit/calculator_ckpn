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

            // Kertas kerja ER: write-off dengan tglwo di dalam rentang 5 tahun
            // DAN tanpa jaminan (penyelesaian via pembayaran cash). Debitur
            // write-off yang punya jaminan aktif menjadi populasi LGD CS.
            $windowLoans = DB::table('snapshot')
                ->where('write_off_flag', 1)
                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                ->whereRaw('SUBSTRING(tglwo, 1, 6) >= ?', [$startYm])
                ->whereRaw('SUBSTRING(tglwo, 1, 6) <= ?', [$assessYm])
                ->distinct()
                ->pluck('loan_number');

            $collateralLoans = DB::table('collateral')
                ->whereIn('loan_number', $windowLoans)
                ->where('active_flag', 1)
                ->distinct()
                ->pluck('loan_number');

            $windowLoans = $windowLoans->diff($collateralLoans)->values();

            $this->pushProgress($token, 'Memuat data write-off historis…', 20);

            $byGroup = [];
            $fallbackCount = 0; // jumlah grup yang memakai LGD fallback (tanpa data write-off)

            if ($windowLoans->isNotEmpty()) {
                // OS awal write-off = snapshot pada periode tglwo
                // (mis. tglwo 20230115 → periode 202301). Bila periode tsb tidak
                // ada snapshot-nya, pakai snapshot pertama SETELAH periode WO.
                $woBalances = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('write_off_flag', 1)
                    ->orderBy('periode_snapshot')
                    ->get(['loan_number', 'periode_snapshot', 'outstanding_balance', 'office_code', 'product_code', 'usage_type', 'financing_contract_code', 'tglwo'])
                    ->groupBy('loan_number')
                    ->map(function ($rows) {
                        $woYm = substr((string) $rows->last()->tglwo, 0, 6);

                        return $rows->first(fn ($r) => substr((string) $r->periode_snapshot, 0, 6) === $woYm)
                            ?? $rows->first(fn ($r) => (string) $r->periode_snapshot >= $woYm)
                            ?? $rows->last();
                    });

                // Posisi outstanding pada periode assessment terakhir.
                $lastBalances = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('periode_snapshot', $assessYm)
                    ->pluck('outstanding_balance', 'loan_number');

                $totalWo = max(1, $woBalances->count());
                $woIdx = 0;

                foreach ($woBalances as $loanNumber => $row) {
                    $woIdx++;
                    if ($token !== null && ($woIdx % 50 === 0 || $woIdx === $totalWo)) {
                        $this->pushProgress($token, "Memproses write-off rekening {$woIdx}/{$totalWo}…", 20 + ($woIdx / $totalWo) * 45, [
                            'current' => $woIdx, 'total' => $totalWo, 'label' => 'Proses Write-off',
                        ]);
                    }
                    $woNominal = (float) $row->outstanding_balance;
                    // Tidak ditemukan pada periode terakhir → debitur lunas dari write-off → recovery 100%.
                    $lastPos = isset($lastBalances[$loanNumber]) ? (float) $lastBalances[$loanNumber] : 0.0;
                    $recovery = max(0.0, $woNominal - $lastPos);

                    // Sync ke tabel write_offs & recoveries
                    $woDate = $row->tglwo ? date('Y-m-d', strtotime($row->tglwo)) : null;
                    if ($woDate) {
                        $writeOff = DB::table('write_offs')->where('financing_id', $loanNumber)->first();
                        $rate = $woNominal > 0 ? min(1.0, $recovery / $woNominal) : 0;
                        $nowTs = now();
                        if (!$writeOff) {
                            $writeOffId = DB::table('write_offs')->insertGetId([
                                'financing_id'   => $loanNumber,
                                'writeoff_date'  => $woDate,
                                'amount'         => $woNominal,
                                'recovery_rate'  => $rate,
                                'reason'         => 'calculated_from_snapshot',
                                'created_at'     => $nowTs,
                                'updated_at'     => $nowTs,
                            ]);
                        } else {
                            $writeOffId = $writeOff->id;
                            DB::table('write_offs')->where('id', $writeOffId)->update([
                                'recovery_rate' => $rate,
                                'updated_at'    => $nowTs,
                            ]);
                        }

                        if ($recovery > 0) {
                            $recDate = $assessmentDate->format('Y-m-d');
                            $existingRec = DB::table('recoveries')->where('write_off_id', $writeOffId)->first();
                            if (!$existingRec) {
                                DB::table('recoveries')->insert([
                                    'write_off_id'    => $writeOffId,
                                    'recovery_date'   => $recDate,
                                    'amount'          => $recovery,
                                    'recovery_source' => 'CASH_PAYMENT_SNAPSHOT',
                                    'created_at'      => $nowTs,
                                    'updated_at'      => $nowTs,
                                ]);
                            } else {
                                DB::table('recoveries')->where('id', $existingRec->id)->update([
                                    'amount'     => $recovery,
                                    'recovery_date' => $recDate,
                                    'updated_at' => $nowTs,
                                ]);
                            }
                        }
                    }

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
            $totalGroups = max(1, $groups->count());
            $gIdx = 0;

            foreach ($groups as $g) {
                $gIdx++;
                $key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);
                if ($token !== null && ($gIdx % 20 === 0 || $gIdx === $totalGroups)) {
                    $this->pushProgress($token, "Menghitung LGD Expected Recovery grup {$gIdx}/{$totalGroups} ({$key})…", 65 + ($gIdx / $totalGroups) * 27, [
                        'current' => $gIdx, 'total' => $totalGroups, 'label' => 'Hitung LGD ER',
                    ]);
                }
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
                        'population' => 'write-off 5 tahun tanpa jaminan (cash)',
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
