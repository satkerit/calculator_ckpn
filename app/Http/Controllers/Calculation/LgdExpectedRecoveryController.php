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
 * Hitung recovery rate historis write-off per grup segmentasi, tracking per tahun.
 *
 * Recovery per tahun = outstanding[y-1] - outstanding[y]:
 *   - Debitur WO 2023 dengan outstanding: 2023=100, 2024=80, 2025=50
 *   - Recovery 2023=20, 2024=30, 2025=50 → total 100 (rate 100%)
 *   - Setiap recovery dicatat recovery_date = akhir tahun tersebut
 *
 * Debitur hilang dari snapshot (outstanding=0) = lunas → recovery 100%.
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

            // LGD ER: populasi = debitur write-off dengan tglwo dalam 5 tahun terakhir
            // DAN tidak memiliki agunan bernilai (likuid <= 0).
            // WO yang beragunan bernilai masuk populasi LGD CS.
            // Pengurang = recovery tunai saja (pergerakan baki debet sejak WO).
            $windowLoans = DB::table('snapshot')
                ->where('write_off_flag', 1)
                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                ->whereRaw('SUBSTRING(tglwo, 1, 6) >= ?', [$startYm])
                ->whereRaw('SUBSTRING(tglwo, 1, 6) <= ?', [$assessYm])
                ->distinct()
                ->pluck('loan_number');

            // WO dengan agunan aktif bernilai → dipindah ke populasi LGD CS.
            $woWithCollateral = $windowLoans->isEmpty() ? collect() : DB::table('collateral')
                ->whereIn('loan_number', $windowLoans)
                ->where('active_flag', 1)
                ->where('liquidity_value', '>', 0)
                ->distinct()
                ->pluck('loan_number');
            $windowLoans = $windowLoans->diff($woWithCollateral)->values();

            $this->pushProgress($token, 'Memuat data write-off historis…', 20);

            $byGroup = [];

            if ($windowLoans->isNotEmpty()) {
                // Ambil semua snapshot WO dalam window, kelompokkan per loan
                $allSnaps = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('write_off_flag', 1)
                    ->orderBy('loan_number')->orderBy('periode_snapshot')
                    ->get(['loan_number', 'periode_snapshot', 'outstanding_balance', 'office_code', 'product_code', 'usage_type', 'financing_contract_code', 'tglwo'])
                    ->groupBy('loan_number');

                $totalWo = max(1, $allSnaps->count());
                $woIdx = 0;
                $nowTs = now();

                foreach ($allSnaps as $loanNumber => $snaps) {
                    $woIdx++;
                    if ($token !== null && ($woIdx % 50 === 0 || $woIdx === $totalWo)) {
                        $this->pushProgress($token, "Memproses write-off rekening {$woIdx}/{$totalWo}…", 20 + ($woIdx / $totalWo) * 45, [
                            'current' => $woIdx, 'total' => $totalWo, 'label' => 'Proses Write-off',
                        ]);
                    }

                    $first = $snaps->first();
                    $woYm = substr((string) $first->tglwo, 0, 6);
                    $woDate = $first->tglwo ? date('Y-m-d', strtotime($first->tglwo)) : null;
                    if (!$woDate) continue;

                    // Snapshot di periode WO atau pertama setelahnya = saldo WO awal
                    $woSnap = $snaps->first(fn($s) => (string)$s->periode_snapshot === $woYm)
                           ?? $snaps->first(fn($s) => (string)$s->periode_snapshot >= $woYm)
                           ?? $first;
                    $woNominal = (float) $woSnap->outstanding_balance;

                    // Upsert write_offs
                    $writeOff = DB::table('write_offs')->where('financing_id', $loanNumber)->first();
                    if (!$writeOff) {
                        $writeOffId = DB::table('write_offs')->insertGetId([
                            'financing_id'   => $loanNumber,
                            'writeoff_date'  => $woDate,
                            'amount'         => $woNominal,
                            'recovery_rate'  => 0,
                            'reason'         => 'calculated_from_snapshot',
                            'created_at'     => $nowTs,
                            'updated_at'     => $nowTs,
                        ]);
                    } else {
                        $writeOffId = $writeOff->id;
                        DB::table('write_offs')->where('id', $writeOffId)->update([
                            'amount' => $woNominal,
                            'updated_at' => $nowTs,
                        ]);
                    }

                    // Hapus recoveries lama untuk recalc
                    DB::table('recoveries')->where('write_off_id', $writeOffId)->delete();

                    // Track recovery per tahun: outstanding[y-1] - outstanding[y]
                    $woYear = (int) substr($woYm, 0, 4);
                    $assessYear = (int) substr($assessYm, 0, 4);
                    $totalRecovery = 0.0;
                    $prevBal = $woNominal;

                    for ($y = $woYear; $y <= $assessYear; $y++) {
                        $endYm = $y.'12';
                        $snapY = $snaps->first(fn($s) => substr((string)$s->periode_snapshot, 0, 4) === (string)$y && (string)$s->periode_snapshot >= $endYm);
                        if (!$snapY && $y === $assessYear) {
                            // Periode assessment bukan Desember, ambil snapshot assessment
                            $snapY = $snaps->first(fn($s) => (string)$s->periode_snapshot === $assessYm);
                        }

                        $curBal = $snapY ? (float) $snapY->outstanding_balance : 0.0;
                        $recoverThisYear = max(0.0, $prevBal - $curBal);

                        if ($recoverThisYear > 0) {
                            DB::table('recoveries')->insert([
                                'write_off_id'    => $writeOffId,
                                'recovery_date'   => $y.'-12-31',
                                'amount'          => $recoverThisYear,
                                'recovery_source' => 'CASH_PAYMENT_YEARLY',
                                'created_at'      => $nowTs,
                                'updated_at'      => $nowTs,
                            ]);
                            $totalRecovery += $recoverThisYear;
                        }

                        $prevBal = $curBal;
                        if ($curBal <= 0) break; // Lunas
                    }

                    // Update recovery_rate di write_offs
                    $rate = $woNominal > 0 ? min(1.0, $totalRecovery / $woNominal) : 0;
                    DB::table('write_offs')->where('id', $writeOffId)->update(['recovery_rate' => $rate]);

                    $akadCode = ProductAkadMappingService::resolveAkadCode($first->product_code ?? '');
                    $usageCode = $first->usage_type ?? $first->financing_contract_code ?? 'OTHER';
                    $key = $this->collectiveGroupKey($dims, $first->office_code, $akadCode, $usageCode);

                    $byGroup[$key]['wo'] = ($byGroup[$key]['wo'] ?? 0.0) + $woNominal;
                    $byGroup[$key]['recovery'] = ($byGroup[$key]['recovery'] ?? 0.0) + $totalRecovery;
                    $byGroup[$key]['count'] = ($byGroup[$key]['count'] ?? 0) + 1;
                }
            }

            $now = now();
            $batch = [];
            $totalGroups = max(1, $groups->count());
            $gIdx = 0;
            $noWoCount = 0;

            foreach ($groups as $g) {
                $gIdx++;
                $key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);
                if ($token !== null && ($gIdx % 20 === 0 || $gIdx === $totalGroups)) {
                    $this->pushProgress($token, "Menghitung LGD Expected Recovery grup {$gIdx}/{$totalGroups} ({$key})…", 65 + ($gIdx / $totalGroups) * 27, [
                        'current' => $gIdx, 'total' => $totalGroups, 'label' => 'Hitung LGD ER',
                    ]);
                }
                $agg = $byGroup[$key] ?? ['wo' => 0.0, 'recovery' => 0.0, 'count' => 0];
                // LGD ER = (WO - recovery tunai) / WO = 1 - recovery_rate.
                $recoveryRate = $agg['wo'] > 0
                    ? min(1.0, max(0.0, $agg['recovery'] / $agg['wo']))
                    : 0.0;
                $lgd = min(1.0, max(0.0, 1.0 - $recoveryRate));
                if ($agg['wo'] == 0.0) {
                    $noWoCount++;
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
                        'population' => 'write-off 5 tahun (recovery tunai saja)',
                        'wo_accounts' => $agg['count'],
                        'total_outstanding_wo' => round($agg['wo'], 2),
                        'total_recovery' => round($agg['recovery'], 2),
                        'note' => 'Komponen jaminan masuk LGD CS (populasi terpisah)',
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
                'no_wo_groups' => $noWoCount,
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
