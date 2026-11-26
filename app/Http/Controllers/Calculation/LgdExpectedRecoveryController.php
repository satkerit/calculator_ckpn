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
            // Range pada kolom tglwo (string yyyymmdd) — bukan SUBSTRING(tglwo,1,6) —
            // supaya index snapshot_wo_tglwo_idx dipakai (range scan, bukan scan semua WO).
            $windowLoans = DB::table('snapshot')
                ->where('write_off_flag', 1)
                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                ->where('tglwo', '>=', $startYm.'01')
                ->where('tglwo', '<=', $assessYm.'31')
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

                // Preload write-off yang sudah ada (1 query, bukan 1 query per debitur).
                $existingWriteOffs = DB::table('write_offs')
                    ->whereIn('financing_id', $windowLoans)
                    ->get(['id', 'financing_id'])
                    ->keyBy('financing_id');

                $writeOffInserts = [];   // baris baru write_offs
                $writeOffUpdates = [];   // [id => nilai kolom yang berubah]
                $writeOffIds = [];       // [loan_number => write_off_id] baris lama
                $recoveryRows = [];      // [loan_number => daftar recovery baru]

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

                    // Indeks snapshot per periode (yyyyymm) sekali per debitur: pencarian
                    // tahunan di bawah menjadi O(1) alih-alih memindai koleksi tiap tahun.
                    $snapByYm = [];
                    foreach ($snaps as $s) {
                        $snapByYm[(string) $s->periode_snapshot] = $s;
                    }

                    // Snapshot di periode WO atau pertama setelahnya = saldo WO awal
                    $woSnap = $snapByYm[$woYm] ?? null;
                    if ($woSnap === null) {
                        foreach ($snapByYm as $ym => $s) {
                            if ((string) $ym >= $woYm) { $woSnap = $s; break; }
                        }
                    }
                    $woSnap ??= $first;
                    $woNominal = (float) $woSnap->outstanding_balance;

                    // Track recovery per tahun: outstanding[y-1] - outstanding[y]
                    $woYear = (int) substr($woYm, 0, 4);
                    $assessYear = (int) substr($assessYm, 0, 4);
                    $totalRecovery = 0.0;
                    $prevBal = $woNominal;

                    for ($y = $woYear; $y <= $assessYear; $y++) {
                        // Saldo akhir tahun y = snapshot Desember tahun y.
                        $snapY = $snapByYm[$y.'12'] ?? null;
                        if (!$snapY && $y === $assessYear) {
                            // Periode assessment bukan Desember, ambil snapshot assessment
                            $snapY = $snapByYm[$assessYm] ?? null;
                        }

                        $curBal = $snapY ? (float) $snapY->outstanding_balance : 0.0;
                        $recoverThisYear = max(0.0, $prevBal - $curBal);

                        if ($recoverThisYear > 0) {
                            // Dikumpulkan dulu; ditulis batch setelah loop (butuh write_off_id).
                            $recoveryRows[$loanNumber][] = [
                                'recovery_date'   => $y.'-12-31',
                                'amount'          => $recoverThisYear,
                                'recovery_source' => 'CASH_PAYMENT_YEARLY',
                            ];
                            $totalRecovery += $recoverThisYear;
                        }

                        $prevBal = $curBal;
                        if ($curBal <= 0) break; // Lunas
                    }

                    // Kumpulkan perubahan write_offs; ditulis batch setelah loop.
                    $rate = $woNominal > 0 ? min(1.0, $totalRecovery / $woNominal) : 0;
                    $existing = $existingWriteOffs[$loanNumber] ?? $existingWriteOffs[(string) $loanNumber] ?? null;

                    if ($existing) {
                        $writeOffIds[(string) $loanNumber] = (int) $existing->id;
                        $writeOffUpdates[(int) $existing->id] = [
                            'amount' => $woNominal,
                            'recovery_rate' => $rate,
                            'updated_at' => $nowTs,
                        ];
                    } else {
                        $writeOffInserts[] = [
                            'financing_id'  => $loanNumber,
                            'writeoff_date' => $woDate,
                            'amount'        => $woNominal,
                            'recovery_rate' => $rate,
                            'reason'        => 'calculated_from_snapshot',
                            'created_at'    => $nowTs,
                            'updated_at'    => $nowTs,
                        ];
                    }

                    $akadCode = ProductAkadMappingService::resolveAkadCode($first->product_code ?? '');
                    $usageCode = $first->usage_type ?? $first->financing_contract_code ?? 'OTHER';
                    $key = $this->collectiveGroupKey($dims, $first->office_code, $akadCode, $usageCode);

                    $byGroup[$key]['wo'] = ($byGroup[$key]['wo'] ?? 0.0) + $woNominal;
                    $byGroup[$key]['recovery'] = ($byGroup[$key]['recovery'] ?? 0.0) + $totalRecovery;
                    $byGroup[$key]['count'] = ($byGroup[$key]['count'] ?? 0) + 1;
                }

                // ---- Tulis write-off & recovery secara batch (bukan per debitur) ----
                $newIds = [];
                if ($writeOffInserts !== []) {
                    foreach (array_chunk($writeOffInserts, 200) as $chunk) {
                        DB::table('write_offs')->insert($chunk);
                    }
                    foreach (DB::table('write_offs')
                        ->whereIn('financing_id', array_column($writeOffInserts, 'financing_id'))
                        ->get(['id', 'financing_id']) as $row) {
                        $newIds[(string) $row->financing_id] = (int) $row->id;
                    }
                }

                foreach ($writeOffUpdates as $id => $values) {
                    DB::table('write_offs')->where('id', $id)->update($values);
                }

                // Hapus recovery lama hanya untuk write-off yang dihitung ulang.
                $affectedIds = array_values(array_unique(array_merge(
                    array_values($writeOffIds),
                    array_values($newIds),
                )));
                foreach (array_chunk($affectedIds, 500) as $chunk) {
                    DB::table('recoveries')->whereIn('write_off_id', $chunk)->delete();
                }

                $recoveryInserts = [];
                foreach ($recoveryRows as $loanNumber => $rows) {
                    $id = $writeOffIds[(string) $loanNumber] ?? $newIds[(string) $loanNumber] ?? null;
                    if ($id === null) {
                        continue;
                    }
                    foreach ($rows as $row) {
                        $recoveryInserts[] = $row + [
                            'write_off_id' => $id,
                            'created_at'   => $nowTs,
                            'updated_at'   => $nowTs,
                        ];
                    }
                }
                foreach (array_chunk($recoveryInserts, 500) as $chunk) {
                    DB::table('recoveries')->insert($chunk);
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
