<?php

namespace App\Http\Controllers\Calculation;

use App\Models\Snapshot;
use App\Services\PdMigration\PeriodResolver;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Modul 4: PD Migration (Quality Rating).
 *
 * Replikasi metode kertas kerja "B2.PD-Migration":
 *   - Matriks sumber 1..5, tujuan 1..5 + Hapus Buku + Pembayaran.
 *   - Bobot = saldo (outstanding_balance) bukan count kontrak.
 *   - State default: Hapus Buku (langsung) + cascade via rating 5/4/3/2.
 *   - Horizon transisi = 12 bulan (tahunan). Rentang historis diturunkan
 *     dari ketersediaan snapshot untuk loan grup (bukan dari konfigurasi
 *     historical_months; konfigurasi itu hanya dipakai oleh PD Netflow).
 *   - PD per rating dihitung backward dari rating 5; PD grup = EAD-weighted
 *     atas PD rating anggota, diambil dari snapshot posisi assessment.
 */
class PdMigrationController extends BaseCalculationController
{
    /** Kode tujuan khusus di matriks. */
    private const DEST_WRITE_OFF = 6;   // = Hapus Buku
    private const DEST_PAID = 7;        // = Pembayaran
    public function index(Request $request): View
    {
        $period = $this->resolvePeriod($request);
        $periods = $this->periods();
        $config = $this->configFor($period->id);
        $results = $this->loadResults($period->id);
        // Hasil basi bila konfigurasi (segmentasi/window/metode PD) berubah setelah run terakhir.
        $pdStale = in_array('PD', $this->staleResultTypes($period->id), true);

        return view('calculation.modules.pd_migration', compact('period', 'periods', 'config', 'results', 'pdStale'));
    }

    public function run(Request $request): RedirectResponse|JsonResponse
    {
        $period = $this->resolvePeriod($request);

        // Lock per-periode + transaksi agar penulisan hasil atomik & tidak bentrok antar user.
        return $this->withRunLock($period->id, fn () => DB::transaction(function () use ($request, $period) {
            $dims = $this->segmentDimensions($request, $period->id);
            $assessmentDate = $period->assessment_date;
            $assessmentYm = $assessmentDate->format('Ym');

            // Hanya hapus hasil MIGRATION (jangan sentuh NET_FLOW periode ini).
            DB::table('stage_pd_results')
                ->where('assessment_period_id', $period->id)
                ->where('method', 'MIGRATION')
                ->delete();

            $groups = $this->loadCollectiveGroups($period->id, $dims)->map(function ($g) use ($dims) {
                $g->group_key = $this->collectiveGroupKey($dims, $g->office_code, $g->akad_code, $g->usage_code);

                return $g;
            });

            if ($groups->isEmpty()) {
                return $this->backTo($request, 'calculation.module.pd_migration', [], 'Belum ada klasifikasi COLLECTIVE.');
            }

            $token = $this->progressToken($request);
            $this->pushProgress($token, 'Memuat snapshot historis…', 5);

            // N+1 fix: muat member per grup sekali (in-memory) alih-alih query per grup.
            $classRows = DB::table('stage_classifications')
                ->where('assessment_period_id', $period->id)
                ->where('classification', 'COLLECTIVE')
                ->get(['loan_number', 'office_code', 'akad_code', 'usage_code']);

            $loansByGroup = [];
            foreach ($classRows as $c) {
                $loansByGroup[$this->collectiveGroupKey($dims, $c->office_code, $c->akad_code, $c->usage_code)][] = $c->loan_number;
            }
            $loanNumbers = $classRows->pluck('loan_number')->unique()->values();

            $this->pushProgress($token, 'Menyusun 4 matriks triwulan…', 15);

            // 4 matriks triwulan per spec PSAK 414 / kertas kerja B2.PD-Migration.
            // Setiap matriks = T-12 → T (tepat 12 bulan); rentang ini tidak
            // mengikuti konfigurasi historical_months (itu hanya untuk Netflow).
            $rawMatrices = PeriodResolver::getMigrationPeriods(Carbon::parse($assessmentDate));
            $matrices = array_map(static function (array $m): array {
                $m['t_start_ym'] = Carbon::parse($m['t_start'])->format('Ym');
                $m['t_end_ym'] = Carbon::parse($m['t_end'])->format('Ym');

                return $m;
            }, $rawMatrices);

            // Snapshot yang dibutuhkan = posisi di t_start & t_end tiap matriks
            // + posisi asesmen (untuk bobot PD grup & deteksi WO/paid).
            $neededYm = array_values(array_unique(array_merge(
                [$assessmentYm],
                array_column($matrices, 't_start_ym'),
                array_column($matrices, 't_end_ym'),
            )));

            $snaps = Snapshot::whereIn('loan_number', $loanNumbers)
                ->whereIn('periode_snapshot', $neededYm)
                ->get()
                ->groupBy(fn ($s) => $s->periode_snapshot.'|'.$s->loan_number);

            $now = now();
            $batch = [];
            $totalGroups = max(1, $groups->count());
            $i = 0;

            foreach ($groups as $g) {
                $i++;
                $this->pushProgress($token, "Menghitung matriks migrasi rating grup {$i}/{$totalGroups} ({$g->group_key})…", 15 + ($i / $totalGroups) * 75, [
                    'current' => $i, 'total' => $totalGroups, 'label' => 'Hitung PD Migration',
                ]);
                $loans = $loansByGroup[$g->group_key] ?? [];
                $detail = $this->computeMigrationPd($snaps, $matrices, $loans, $assessmentYm);
                $pdRate = $detail['pd_group'];

                $batch[] = [
                    'assessment_period_id' => $period->id,
                    'group_key' => $g->group_key,
                    'pd_calculated' => round($pdRate, 6),
                    'method' => 'MIGRATION',
                    'periods_analyzed' => $detail['matrices_used'],
                    'detail' => json_encode([
                        'method' => 'MIGRATION',
                        'spec' => 'KERTAS_KERJA',
                        'dims' => implode('|', $dims),
                        'transition_horizon_months' => 12,
                        'matrices' => $detail['matrices'],
                        'matrices_used' => $detail['matrices_used'],
                        // Aggregate hanya untuk tampilan cepat; PD diambil dari weighted average.
                        'matrix' => $detail['matrix'],
                        'rating_pd' => $detail['rating_pd'],
                        'rating_exposure' => $detail['rating_exposure'],
                        'matrix_total_exposure' => $detail['matrix_total'],
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            // Replace existing MIGRATION rows for this period.
            $this->pushProgress($token, 'Menyimpan hasil PD Migration…', 95);
            foreach (array_chunk($batch, 200) as $chunk) {
                $this->safeInsert('stage_pd_results', $chunk);
            }

            // Tandai hasil PD sah dengan konfigurasi yang dipakai saat run (fingerprint).
            $this->markResults($period->id, 'PD', ['segment_dimensions' => $dims]);

            $matrixLabels = array_map(static fn (array $m) => $m['quarter'].' ('.$m['t_start_ym'].'→'.$m['t_end_ym'].')', $matrices);

            $this->audit('PD_MIGRATION', 'RUN', $period->id, [
                'groups' => $groups->count(),
                'matrices' => $matrixLabels,
                'inserted' => count($batch),
            ]);

            return $this->backTo($request, 'calculation.module.pd_migration', [
                'groups' => $groups->count(),
                'method' => 'MIGRATION',
                'matriks' => implode(' · ', $matrixLabels),
            ]);
        }));
    }

    /**
     * Hitung PD per formula kertas kerja B2.PD-Migration (4 matriks triwulan).
     *
     * Untuk setiap matriks m (T-12 → T):
     *   1. Akumulasi saldo (bobot rupiah) rating sumber 1..5 ke tujuan
     *      1..5 / Hapus Buku / Pembayaran.
     *   2. Hitung migration rate per sumber: saldo tujuan / saldo awal.
     *   3. Cascade backward dari rating 5:
     *        PD5 = H5 + G5
     *        PD4 = H4 + (G4*PD5)
     *        PD3 = H3 + (G3*PD5) + (F3*PD4)
     *        PD2 = H2 + (G2*PD5) + (F2*PD4) + (E2*PD3)
     *        PD1 = H1 + (G1*PD5) + (F1*PD4) + (E1*PD3) + (D1*PD2)
     *      dengan H=Hapus Buku, G=ke-5, F=ke-4, E=ke-3, D=ke-2.
     *   4. PD rating final = weighted average saldo awal lintas matriks:
     *        PD_k = Σ(SaldoAwal_k,m × PD_k,m) / Σ(SaldoAwal_k,m)
     *   5. PD grup = EAD-weighted atas exposure tiap rating di posisi asesmen.
     *
     * @return array{pd_group:float,rating_pd:array<int,float>,matrices:array,matrices_used:int,matrix:array,rating_exposure:array<int,float>,matrix_total:float}
     */
    private function computeMigrationPd(Collection $snaps, array $matrices, array $loanNumbers, string $assessmentYm): array
    {
        $zeroRating = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];

        // Saldo awal rating k per matriks m, dan PD rating k per matriks m.
        $saldoAwalByRating = [];      // [m][rating] = float
        $pdByMatrix = [];             // [m][rating] = float (cascade result)
        $matrixSnapshots = [];        // [m] = ['matrix' => 5x7 raw, 'rate' => 5x7 rate, 'saldo_awal' => 5]
        $matrixTotal = 0.0;
        $matricesUsed = 0;

        foreach ($matrices as $idx => $m) {
            $startYm = $m['t_start_ym'];
            $endYm = $m['t_end_ym'];

            $matrix = [];
            $rowTotals = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
            foreach ($loanNumbers as $ln) {
                $curRows = $snaps->get($startYm.'|'.$ln);
                $cur = $curRows instanceof Collection ? $curRows->first() : $curRows;
                if (! $cur || (bool) ($cur->write_off_flag ?? false)) {
                    // t_start hanya debitur aktif non-WO, rating 1..5.
                    continue;
                }
                $from = $this->ratingBucket($cur->quality_rating);
                $startBalance = (float) ($cur->outstanding_balance ?? 0);
                if ($startBalance < 0) {
                    $startBalance = 0;
                }
                // Saldo Awal (kolom B kertas kerja) = posisi t_start per rating.
                $rowTotals[$from] += $startBalance;

                $nextRows = $snaps->get($endYm.'|'.$ln);
                $next = $nextRows instanceof Collection ? $nextRows->first() : $nextRows;
                if (! $next) {
                    // Debitur lenyap di t_end: saldo awal tidak di-place ke sel manapun,
                    // menjadi Pembayaran via komputasi residual per baris (selisih).
                    continue;
                }
                // Sel matriks (C..H kertas kerja) = outstanding posisi t_end.
                // Saldo awal tetap di $rowTotals dari t_start.
                $endBalance = max(0.0, (float) ($next->outstanding_balance ?? 0));
                $to = $this->destState($cur, $next);
                if ($to === self::DEST_PAID) {
                    // Lunas di t_end: jangan place langsung, serap via residual
                    // agar Pembayaran murni = Saldo Awal - Σ(1..5) - WO.
                    continue;
                }
                $matrix[$from][$to] = ($matrix[$from][$to] ?? 0) + $endBalance;
            }

            $rowSum = array_sum($rowTotals);
            if ($rowSum <= 0) {
                continue;
            }

            // Pembayaran = residual per baris sumber:
            // Saldo Awal[from] - Σ tujuan 1..5 - Hapus Buku[from].
            // Loan yang lenyap di t_end otomatis terserap di sini.
            for ($r = 1; $r <= 5; $r++) {
                $sumToRating = 0.0;
                for ($t = 1; $t <= 5; $t++) {
                    $sumToRating += $matrix[$r][$t] ?? 0;
                }
                $wo = $matrix[$r][self::DEST_WRITE_OFF] ?? 0;
                $candidate = $rowTotals[$r] - $sumToRating - $wo;
                $matrix[$r][self::DEST_PAID] = $candidate > 0 ? $candidate : 0.0;
            }

            // Padatkan matriks agar tiap baris 1..5 punya semua kolom 1..7
            // (blade/export akses langsung tanpa ?? di beberapa titik).
            for ($r = 1; $r <= 5; $r++) {
                for ($t = 1; $t <= self::DEST_PAID; $t++) {
                    $matrix[$r][$t] = $matrix[$r][$t] ?? 0.0;
                }
                ksort($matrix[$r]);
            }

            // Rate per sel = saldo tujuan / saldo awal sumber.
            $rate = [];
            for ($r = 1; $r <= 5; $r++) {
                for ($t = 1; $t <= self::DEST_PAID; $t++) {
                    $rate[$r][$t] = $rowTotals[$r] > 0 ? ($matrix[$r][$t] ?? 0) / $rowTotals[$r] : 0.0;
                }
            }

            // Cascade backward per matriks.
            $pd = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
            $h = fn ($r) => $rate[$r][self::DEST_WRITE_OFF];
            $g = fn ($r) => $rate[$r][5];
            $f = fn ($r) => $rate[$r][4];
            $e = fn ($r) => $rate[$r][3];
            $d = fn ($r) => $rate[$r][2];
            $pd[5] = $h(5) + $g(5);
            $pd[4] = $h(4) + $g(4) * $pd[5];
            $pd[3] = $h(3) + $g(3) * $pd[5] + $f(3) * $pd[4];
            $pd[2] = $h(2) + $g(2) * $pd[5] + $f(2) * $pd[4] + $e(2) * $pd[3];
            $pd[1] = $h(1) + $g(1) * $pd[5] + $f(1) * $pd[4] + $e(1) * $pd[3] + $d(1) * $pd[2];

            foreach ($pd as $r => $v) {
                $pdByMatrix[$idx][$r] = min(1.0, max(0.0, $v));
                $saldoAwalByRating[$idx][$r] = $rowTotals[$r];
            }
            // Total kolom tujuan (t1..t7) lintas rating sumber.
            $colTotals = [];
            for ($t = 1; $t <= self::DEST_PAID; $t++) {
                $colTotals[$t] = 0.0;
                for ($r = 1; $r <= 5; $r++) {
                    $colTotals[$t] += $matrix[$r][$t] ?? 0;
                }
            }

            // Total baris = SUM(C..I) kertas kerja (kolom J). Harus = Saldo Awal baris.
            $rowCellTotals = [];
            for ($r = 1; $r <= 5; $r++) {
                $sum = 0.0;
                for ($t = 1; $t <= self::DEST_PAID; $t++) {
                    $sum += $matrix[$r][$t] ?? 0;
                }
                $rowCellTotals[$r] = $sum;
            }

            // Pembayaran = residual per baris (saldo awal - tujuan 1..5 - hapus buku).
            $matrixSnapshots[$idx] = [
                'quarter' => $m['quarter'],
                't_start' => $m['t_start'],
                't_end' => $m['t_end'],
                't_start_label' => Carbon::parse($m['t_start'])->format('Y-m-d'),
                't_end_label' => Carbon::parse($m['t_end'])->format('Y-m-d'),
                't_start_ym' => $startYm,
                't_end_ym' => $endYm,
                'matrix' => $matrix,
                'rate' => $rate,
                'saldo_awal' => $rowTotals,
                'total_from' => $rowTotals,
                'total_to' => $colTotals,
                'row_total' => $rowCellTotals,
                'grand_total' => $rowSum,
                'pd' => $pd,
            ];
            $matrixTotal += $rowSum;
            $matricesUsed++;
        }

        if ($matricesUsed === 0) {
            return [
                'pd_group' => 0.0,
                'rating_pd' => $zeroRating,
                'matrices' => [],
                'matrices_used' => 0,
                'matrix' => [],
                'rating_exposure' => $zeroRating,
                'matrix_total' => 0.0,
            ];
        }

        // Weighted average saldo awal lintas matriks: PD_k = Σ(SaldoAwal × PD) / Σ(SaldoAwal).
        $ratingPd = $zeroRating;
        for ($r = 1; $r <= 5; $r++) {
            $num = 0.0;
            $den = 0.0;
            foreach ($matrices as $idx => $_) {
                if (! isset($saldoAwalByRating[$idx][$r])) {
                    continue;
                }
                $num += $saldoAwalByRating[$idx][$r] * $pdByMatrix[$idx][$r];
                $den += $saldoAwalByRating[$idx][$r];
            }
            $ratingPd[$r] = $den > 0 ? round($num / $den, 6) : 0.0;
        }

        // Matriks rate rata-rata tertimbang saldo awal (untuk UI drill-down agregat).
        $matrixAcc = [];
        $rowTotalAcc = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0];
        foreach ($matrixSnapshots as $snap) {
            for ($r = 1; $r <= 5; $r++) {
                $rowTotalAcc[$r] += $snap['saldo_awal'][$r];
                for ($t = 1; $t <= self::DEST_PAID; $t++) {
                    $matrixAcc[$r][$t] = ($matrixAcc[$r][$t] ?? 0) + ($snap['matrix'][$r][$t] ?? 0);
                }
            }
        }
        $matrixAvg = [];
        for ($r = 1; $r <= 5; $r++) {
            $den = $rowTotalAcc[$r];
            for ($t = 1; $t <= self::DEST_PAID; $t++) {
                $matrixAvg[$r][$t] = $den > 0 ? round(($matrixAcc[$r][$t] ?? 0) / $den, 6) : 0.0;
            }
        }

        // Bobot exposure posisi asesmen per rating → PD grup.
        $assessExp = $zeroRating;
        foreach ($loanNumbers as $ln) {
            $rows = $snaps->get($assessmentYm.'|'.$ln);
            $snap = $rows instanceof Collection ? $rows->first() : $rows;
            if (! $snap) {
                continue;
            }
            $r = $this->ratingBucket($snap->quality_rating);
            $bal = (float) ($snap->outstanding_balance ?? 0);
            $assessExp[$r] += max(0, $bal);
        }
        $totalExp = array_sum($assessExp);
        if ($totalExp > 0) {
            $pdGroup = 0.0;
            foreach ($assessExp as $r => $exp) {
                $pdGroup += ($exp / $totalExp) * $ratingPd[$r];
            }
        } else {
            // Fallback: rata-rata PD rating.
            $pdGroup = array_sum($ratingPd) / 5;
        }

        return [
            'pd_group' => min(1.0, max(0.0, $pdGroup)),
            'rating_pd' => $ratingPd,
            'matrices' => array_values($matrixSnapshots),
            'matrices_used' => $matricesUsed,
            'matrix' => $matrixAvg,
            'rating_exposure' => $assessExp,
            'matrix_total' => $matrixTotal,
        ];
    }

    /**
     * Tentukan state tujuan dari sepasang snapshot.
     *
     * Kertas kerja memisahkan Hapus Buku dan Pembayaran dari rating 1..5.
     * Deteksi: write_off_flag → Hapus Buku; saldo habis dengan active_flag=false
     * → Pembayaran; sisanya rating tujuan.
     */
    private function destState(object $cur, object $next): int
    {
        $wasWO = (bool) ($cur->write_off_flag ?? false);
        $isWO = (bool) ($next->write_off_flag ?? false);
        if ($isWO || $wasWO) {
            return self::DEST_WRITE_OFF;
        }
        $nextBal = (float) ($next->outstanding_balance ?? 0);
        $nextArrears = (float) ($next->principal_arrears ?? 0);
        if (! (bool) ($next->active_flag ?? true) && $nextBal <= 0 && $nextArrears <= 0) {
            return self::DEST_PAID;
        }

        return $this->ratingBucket($next->quality_rating);
    }

    /**
     * Map quality_rating ke 5 bucket: 1=lancar, 2=DPK, 3=mgd, 4=macet, 5=WO.
     */
    private function ratingBucket(?string $rating): int
    {
        $r = (int) ($rating ?? 1);

        return max(1, min(5, $r));
    }

    private function loadResults(int $periodId): array
    {
        $rows = DB::table('stage_pd_results')
            ->where('assessment_period_id', $periodId)
            ->where('method', 'MIGRATION')
            ->orderBy('pd_calculated', 'desc')
            ->get();

        $avg = $rows->isEmpty() ? 0.0 : (float) $rows->avg('pd_calculated');

        // Decode JSON detail agar blade bisa render matriks per grup.
        $rows->transform(function ($r) {
            $d = json_decode($r->detail ?? '{}', true) ?: [];
            $r->rating_pd = $d['rating_pd'] ?? [];
            $r->matrix = $d['matrix'] ?? [];
            $r->matrices = $d['matrices'] ?? [];
            // Alias untuk blade: daftar 4 matriks (outstanding + rate) per triwulan.
            $r->matrixSnapshots = $r->matrices;
            $r->rating_exposure = $d['rating_exposure'] ?? [];

            return $r;
        });

        return [
            'rows' => $rows,
            'count' => $rows->count(),
            'avg_pd' => round($avg, 6),
            'method' => 'MIGRATION',
        ];
    }
}
