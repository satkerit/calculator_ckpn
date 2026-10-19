<?php

namespace App\Http\Controllers\Calculation;

use App\Services\ProductAkadMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Detail LGD per debitur (ER & CS).
 *
 * Menampilkan tiap debitur write-off dalam window historis beserta
 * pergerakan penurunan outstanding per tahun: dari OS awal saat write-off
 * sampai posisi outstanding pada periode assessment (recovery ter realizasi).
 */
class LgdDetailController extends BaseCalculationController
{
    public function show(Request $request, string $method): View
    {
        abort_unless(in_array(strtoupper($method), ['ER', 'CS'], true), 404);

        $method = strtoupper($method);
        $period = $this->resolvePeriod($request);
        $config = $this->configFor($period->id);
        $dims = $this->segmentDimensions($request, $period->id);

        // Guard: harus sudah ada hasil LGD untuk method ini pada periode terpilih.
        $hasResult = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $method)
            ->exists();

        if (! $hasResult) {
            $backRoute = $method === 'ER' ? 'calculation.module.lgd_er' : 'calculation.module.lgd_cs';

            return view('calculation.lgd_detail', [
                'method' => $method,
                'period' => $period,
                'periods' => $this->periods(),
                'config' => $config,
                'debtorRows' => collect(),
                'yearlyRows' => collect(),
                'groups' => collect(),
                'selectedGroup' => $request->query('group_key'),
                'emptyNotice' => "Belum ada hasil LGD {$method}. Jalankan perhitungan LGD {$method} terlebih dahulu.",
                'backRoute' => $backRoute,
                'histM' => (int) ($config['lgd_historical_months'] ?? 60),
                'saleCostPct' => (float) $config['sale_cost_percentage'],
            ]);
        }

        $histM = (int) ($config['lgd_historical_months'] ?? 60);
        $saleCostPct = (float) $config['sale_cost_percentage'];
        $assessmentDate = $period->assessment_date;
        $assessYm = $assessmentDate->format('Ym');
        $startYm = $assessmentDate->copy()->subMonths($histM)->format('Ym');

        if ($method === 'CS') {
            // Populasi CS: pembiayaan kualitas 5 ATAU write-off, masih aktif
            // pada periode assessment, DAN wajib berjamin aktif (eksekusi jaminan).
            // quality_rating string → cast int.
            $windowLoans = DB::table('snapshot')
                ->where('periode_snapshot', $assessYm)
                ->where('active_flag', 1)
                ->where(function ($q) {
                    $q->where('write_off_flag', 1)
                        ->orWhereRaw('CAST(quality_rating AS UNSIGNED) = 5');
                })
                ->distinct()
                ->pluck('loan_number');

            // Wajib berjamin: hanya loan dengan jaminan aktif.
            $withCollateral = DB::table('collateral')
                ->whereIn('loan_number', $windowLoans)
                ->where('active_flag', 1)
                ->distinct()
                ->pluck('loan_number');
            $windowLoans = $windowLoans->intersect($withCollateral)->values();
        } else {
            // Window write-off sama dengan modul LGD ER: write-off 5 tahun
            // TANPA jaminan aktif (penyelesaian cash). WO berjamin → CS.
            $windowLoans = DB::table('snapshot')
                ->where('write_off_flag', 1)
                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                ->where('tglwo', '>=', $startYm.'01')
                ->where('tglwo', '<=', $assessYm.'31')
                ->distinct()
                ->pluck('loan_number');

            $withCollateral = DB::table('collateral')
                ->whereIn('loan_number', $windowLoans)
                ->where('active_flag', 1)
                ->distinct()
                ->pluck('loan_number');
            $windowLoans = $windowLoans->diff($withCollateral)->values();
        }

        $debtorRows = collect();
        $yearlyRows = collect();

        if ($windowLoans->isNotEmpty()) {
            $attrs = ['loan_number', 'periode_snapshot', 'outstanding_balance', 'office_code', 'product_code', 'usage_type', 'financing_contract_code', 'tglwo', 'write_off_flag', 'quality_rating'];

            if ($method === 'CS') {
                // Baki debet & atribut = snapshot periode assessment.
                $baseRows = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('periode_snapshot', $assessYm)
                    ->get($attrs)
                    ->keyBy('loan_number');
            } else {
                // OS awal write-off = snapshot pada periode tglwo (mis. tglwo
                // 20230115 → periode 202301). Fallback: snapshot pertama setelah WO.
                $baseRows = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('write_off_flag', 1)
                    ->orderBy('periode_snapshot')
                    ->get($attrs)
                    ->groupBy('loan_number')
                    ->map(function ($rows) {
                        $woYm = substr((string) $rows->last()->tglwo, 0, 6);

                        return $rows->first(fn ($r) => substr((string) $r->periode_snapshot, 0, 6) === $woYm)
                            ?? $rows->first(fn ($r) => (string) $r->periode_snapshot >= $woYm)
                            ?? $rows->last();
                    });
            }

            // OS posisi periode assessment (loan tidak ada → lunas/setelah WO → 0).
            $lastBalances = DB::table('snapshot')
                ->whereIn('loan_number', $windowLoans)
                ->where('periode_snapshot', $assessYm)
                ->pluck('outstanding_balance', 'loan_number');

            // Seluruh riwayat snapshot untuk pergerakan OS per tahun:
            // ambil outstanding TERAKHIR tiap (loan, tahun).
            $histRows = DB::table('snapshot')
                ->whereIn('loan_number', $windowLoans)
                ->orderBy('periode_snapshot')
                ->get(['loan_number', 'periode_snapshot', 'outstanding_balance']);

            $yearlyByLoan = [];
            foreach ($histRows as $h) {
                $year = substr((string) $h->periode_snapshot, 0, 4);
                $yearlyByLoan[$h->loan_number][$year] = (float) $h->outstanding_balance; // terakhir menang
            }
            unset($histRows);

            // Nilai agunan per loan (untuk mode CS) — hanya jaminan aktif.
            $collateralByLoan = $method === 'CS'
                ? DB::table('collateral')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('active_flag', 1)
                    ->selectRaw('loan_number, COALESCE(SUM(liquidity_value), 0) as liq_value')
                    ->groupBy('loan_number')
                    ->pluck('liq_value', 'loan_number')
                : collect();

            $selectedGroup = $request->query('group_key');
            $assessYear = $assessmentDate->format('Y');
            $now = now();

            foreach ($baseRows as $loanNumber => $row) {
                $akadCode = ProductAkadMappingService::resolveAkadCode($row->product_code ?? '');
                $usageCode = $row->usage_type ?? $row->financing_contract_code ?? 'OTHER';
                $groupKey = $this->collectiveGroupKey($dims, $row->office_code, $akadCode, $usageCode);

                // Filter opsional per grup segmentasi.
                if ($selectedGroup !== null && $selectedGroup !== '' && $selectedGroup !== $groupKey) {
                    continue;
                }

                $woNominal = (float) $row->outstanding_balance;
                $woYear = substr((string) $row->tglwo, 0, 4);
                if ($method === 'CS') {
                    // Kualitas 5 belum WO → pakai tahun snapshot pertama loan.
                    $firstYear = array_key_first($yearlyByLoan[$loanNumber] ?? []);
                    $woYear = $woYear !== '' ? $woYear : (string) ($firstYear ?? $assessYear);
                }
                $currentOs = isset($lastBalances[$loanNumber]) ? (float) $lastBalances[$loanNumber] : 0.0;
                $recovery = max(0.0, $woNominal - $currentOs);

                // Pergerakan OS per tahun: dari tahun WO s/d tahun assessment.
                $movement = [];
                $carry = $woNominal;
                for ($y = (int) $woYear; $y <= (int) $assessYear; $y++) {
                    if (isset($yearlyByLoan[$loanNumber][(string) $y])) {
                        $carry = $yearlyByLoan[$loanNumber][(string) $y];
                    }
                    $os = max(0.0, $carry);
                    $prev = $movement === [] ? $woNominal : end($movement)['os'];
                    $movement[] = [
                        'year' => (string) $y,
                        'os' => $os,
                        'penurunan' => max(0.0, $prev - $os),
                        'recovery' => max(0.0, $woNominal - $os),
                        'pct' => $woNominal > 0 ? min(100.0, max(0.0, ($woNominal - $os) / $woNominal * 100)) : 0.0,
                    ];
                }

                // Agregat penurunan per tahun lintas debitur.
                foreach ($movement as $m) {
                    $yearlyRows[$m['year']] = ($yearlyRows[$m['year']] ?? 0.0) + $m['os'];
                }

                $liq = (float) ($collateralByLoan[$loanNumber] ?? 0);
                $saleCost = round($liq * $saleCostPct, 2);
                $netRecovery = round($liq - $saleCost, 2);
                // Shortfall = baki debet − (nilai agunan − agunan × haircut).
                $shortfall = max(0.0, $woNominal - $netRecovery);

                $debtorRows->push((object) [
                    'loan_number' => $loanNumber,
                    'group_key' => $groupKey,
                    'office_code' => $row->office_code,
                    'akad_code' => $akadCode,
                    'usage_code' => $usageCode,
                    'tglwo' => (string) $row->tglwo,
                    'wo_year' => $woYear,
                    'wo_nominal' => $woNominal,
                    // Status populasi: WO = write-off; K5 = Kol 5 (kualitas 5) belum WO.
                    'is_write_off' => (int) ($row->write_off_flag ?? 0) === 1,
                    'quality_rating' => (string) ($row->quality_rating ?? ''),
                    'population_status' => (int) ($row->write_off_flag ?? 0) === 1 ? 'WO' : 'K5',
                    'current_os' => $method === 'CS' ? $woNominal : $currentOs,
                    'recovery' => $method === 'CS' ? $netRecovery : $recovery,
                    'recovery_pct' => $woNominal > 0
                        ? min(100.0, ($method === 'CS' ? $netRecovery : $recovery) / $woNominal * 100)
                        : 0.0,
                    'liquidity_value' => $liq,
                    'sale_cost' => $saleCost,
                    'net_recovery' => $netRecovery,
                    'shortfall' => $shortfall,
                    'shortfall_pct' => $woNominal > 0 ? min(100.0, $shortfall / $woNominal * 100) : 0.0,
                    'movement' => $movement,
                ]);
            }

            // Urut: shortfall/recovery terbesar dulu.
            $debtorRows = $debtorRows->sortByDesc($method === 'CS' ? 'shortfall' : 'recovery')->values();

            $yearlyRows = $yearlyRows
                ->sortKeys()
                ->map(fn ($os, $year) => (object) ['year' => $year, 'total_os' => $os])
                ->values();
        }

        // Daftar grup utk dropdown filter (dari hasil LGD tersimpan).
        $groups = DB::table('stage_lgd_results')
            ->where('assessment_period_id', $period->id)
            ->where('method', $method)
            ->orderBy('group_key')
            ->pluck('group_key');

        return view('calculation.lgd_detail', [
            'method' => $method,
            'period' => $period,
            'periods' => $this->periods(),
            'config' => $config,
            'debtorRows' => $debtorRows,
            'yearlyRows' => $yearlyRows,
            'groups' => $groups,
            'selectedGroup' => $request->query('group_key'),
            'emptyNotice' => null,
            'backRoute' => $method === 'ER' ? 'calculation.module.lgd_er' : 'calculation.module.lgd_cs',
            'histM' => $histM,
            'saleCostPct' => $saleCostPct,
        ]);
    }
}
