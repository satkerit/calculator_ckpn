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
        $prevYm = $assessmentDate->copy()->subMonth()->format('Ym');

        // Jaminan aktif bernilai → penentu pembagian populasi ER vs CS.
        $loansWithValuedCollateral = fn ($loans) => $loans->isEmpty() ? collect() : DB::table('collateral')
            ->whereIn('loan_number', $loans)
            ->where('active_flag', 1)
            ->where('liquidity_value', '>', 0)
            ->distinct()
            ->pluck('loan_number');

        if ($method === 'CS') {
            // Populasi CS = Kol 5 aktif pada periode assessment
            //            + WO 5 tahun terakhir yang PUNYA agunan bernilai.
            $k5Loans = DB::table('snapshot')
                ->where('periode_snapshot', $assessYm)
                ->where('active_flag', 1)
                ->whereRaw('CAST(quality_rating AS UNSIGNED) = 5')
                ->where('write_off_flag', '<>', 1)
                ->distinct()
                ->pluck('loan_number');

            $woLoans = DB::table('snapshot')
                ->where('write_off_flag', 1)
                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                ->whereRaw('SUBSTRING(tglwo, 1, 6) >= ?', [$startYm])
                ->whereRaw('SUBSTRING(tglwo, 1, 6) <= ?', [$assessYm])
                ->distinct()
                ->pluck('loan_number');

            $windowLoans = $k5Loans->merge($loansWithValuedCollateral($woLoans))->unique()->values();
        } else {
            // Populasi ER = WO 5 tahun terakhir TANPA agunan bernilai.
            $windowLoans = DB::table('snapshot')
                ->where('write_off_flag', 1)
                ->whereNotNull('tglwo')->where('tglwo', '<>', '')
                ->where('tglwo', '>=', $startYm.'01')
                ->where('tglwo', '<=', $assessYm.'31')
                ->distinct()
                ->pluck('loan_number');

            $windowLoans = $windowLoans->diff($loansWithValuedCollateral($windowLoans))->values();
        }

        $debtorRows = collect();
        $yearlyRows = collect();

        if ($windowLoans->isNotEmpty()) {
            $attrs = ['loan_number', 'periode_snapshot', 'outstanding_balance', 'office_code', 'product_code', 'usage_type', 'financing_contract_code', 'tglwo', 'write_off_flag', 'quality_rating'];

            // OS awal write-off (snapshot periode tglwo).
            $woBalances = DB::table('snapshot')
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

            if ($method === 'CS') {
                // Info dasar debitur: ambil snapshot periode assessment.
                $baseRows = DB::table('snapshot')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('periode_snapshot', $assessYm)
                    ->get($attrs)
                    ->keyBy('loan_number');
            } else {
                $baseRows = $woBalances;
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

            $assessYear = $assessmentDate->format('Y');
            // Fallback: jika debitur tidak ada snapshot assessment period, anggap lunas (outstanding=0)
            // Untuk LGD ER: debitur hilang setelah periode terakhir = lunas (recovery 100%)
            foreach ($windowLoans as $ln) {
                $maxPeriode = DB::table('snapshot')
                    ->where('loan_number', $ln)
                    ->max('periode_snapshot');
                $assessYearStr = (string) $assessYear;
                if (! isset($yearlyByLoan[$ln][$assessYearStr]) && $maxPeriode && $maxPeriode < $assessYm) {
                    // Ada data lama, tapi tidak ada snapshot assessment period → lunas
                    $yearlyByLoan[$ln][$assessYearStr] = 0.0;
                }
            }

            // Nilai agunan per loan (mode CS) — hanya jaminan aktif bernilai.
            $collateralByLoan = $method === 'CS'
                ? DB::table('collateral')
                    ->whereIn('loan_number', $windowLoans)
                    ->where('active_flag', 1)
                    ->where('liquidity_value', '>', 0)
                    ->selectRaw('loan_number, COALESCE(SUM(liquidity_value), 0) as liq_value')
                    ->groupBy('loan_number')
                    ->pluck('liq_value', 'loan_number')
                : collect();

            // Baki debet Kol 5 = posisi 1 bulan terakhir sebelum periode assessment.
            $prevBalances = $method === 'CS'
                ? DB::table('snapshot')->where('periode_snapshot', $prevYm)->pluck('outstanding_balance', 'loan_number')
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
                $currentOs = isset($lastBalances[$loanNumber]) ? (float) $lastBalances[$loanNumber] : 0.0;
                $recovery = max(0.0, $woNominal - $currentOs);
                $isWo = (int) ($row->write_off_flag ?? 0) === 1;

                if ($method === 'CS') {
                    // LGD CS rumus baru:
                    // Eksposur = baki debet H-1 assessment + outstanding WO (jika WO)
                    // Shortfall = total baki + total WO - (net likuidasi + recovery WO sejak WO)
                    $bakiDebetH1 = (float) ($prevBalances[$loanNumber] ?? $row->outstanding_balance);
                    $woOutstanding = $isWo ? (isset($woBalances[$loanNumber]) ? (float) $woBalances[$loanNumber]->outstanding_balance : $woNominal) : 0.0;
                    $exposure = $bakiDebetH1 + $woOutstanding;

                    $liq = (float) ($collateralByLoan[$loanNumber] ?? 0.0);
                    $netLikuidasi = max(0.0, $liq * (1 - $saleCostPct));
                    $recoveryWo = $isWo ? max(0.0, $woOutstanding - $currentOs) : 0.0;
                    
                    // Shortfall = (baki + WO) - (net likuidasi + recovery WO)
                    $loss = max(0.0, $exposure - ($netLikuidasi + $recoveryWo));
                    $recovery = $netLikuidasi + $recoveryWo;
                    $cashRec = $recoveryWo;
                    $netRecovery = $netLikuidasi;
                    $woYear = $isWo ? $woYear : '(K5)';
                } else {
                    $exposure = $woNominal;
                    $liq = 0.0;
                    $netRecovery = 0.0;
                    $cashRec = 0.0;
                    $recovery = max(0.0, $woNominal - $currentOs);
                    $loss = $recovery;
                }

                // Pergerakan OS per tahun: dari tahun WO s/d tahun assessment.
                $movement = [];
                $carry = $method === 'CS' && ! $isWo ? $exposure : $woNominal;
                for ($y = (int) $woYear; $y <= (int) $assessYear; $y++) {
                    if (isset($yearlyByLoan[$loanNumber][(string) $y])) {
                        $carry = $yearlyByLoan[$loanNumber][(string) $y];
                    } elseif ($y > (int) $woYear) {
                        // Jika tahun tidak ada di snapshot: debitur lunas/hilang, OS = 0
                        $carry = 0.0;
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

                $saleCostAmt = round($liq * $saleCostPct, 2);

                $debtorRows->push((object) [
                    'loan_number' => $loanNumber,
                    'group_key' => $groupKey,
                    'office_code' => $row->office_code,
                    'akad_code' => $akadCode,
                    'usage_code' => $usageCode,
                    'tglwo' => (string) $row->tglwo,
                    'wo_year' => $woYear,
                    'wo_nominal' => $method === 'CS' ? $exposure : $woNominal,
                    'exposure' => $exposure,
                    'cash_recovery' => $method === 'CS' ? $cashRec : $recovery,
                    // Status populasi: WO = write-off; K5 = Kol 5 (kualitas 5) belum WO.
                    'is_write_off' => $isWo,
                    'quality_rating' => (string) ($row->quality_rating ?? ''),
                    'population_status' => $isWo ? 'WO' : 'K5',
                    'current_os' => $currentOs,
                    'recovery' => $recovery,
                    'liquidity_value' => $liq,
                    'sale_cost' => $saleCostAmt,
                    'net_recovery' => $netRecovery,
                    'shortfall' => $loss,
                    'shortfall_pct' => $exposure > 0 ? min(100.0, $loss / $exposure * 100) : 0.0,
                    'recovery_pct' => $exposure > 0
                        ? min(100.0, ($method === 'CS' ? ($netRecovery + $cashRec) : $recovery) / $exposure * 100)
                        : 0.0,
                    'movement' => $movement,
                ]);
            }

            // Urut: shortfall/recovery terbesar dulu.
            $debtorRows = $debtorRows->sortByDesc($method === 'CS' ? 'shortfall' : 'recovery')->values();

            // Struktur Kertas Kerja B3 (LGD Expected Recoveries): per kohort tahun hapus buku.
            $erCohorts = collect();
            $erTotalCombine = null;
            if ($method === 'ER' && $debtorRows->isNotEmpty()) {
                // Rentang tahun window (mis. 2021 s/d 2026).
                $allYears = [];
                for ($y = (int) substr($startYm, 0, 4); $y <= (int) $assessYear; $y++) {
                    $allYears[] = (string) $y;
                }

                $groupedByYear = $debtorRows->groupBy('wo_year')->sortKeys();
                foreach ($groupedByYear as $woY => $debList) {
                    $cohortDebs = [];
                    foreach ($debList as $d) {
                        $sal = [];
                        $rec = [];
                        $prevOs = (float) $d->wo_nominal;
                        foreach ($allYears as $yStr) {
                            if ((int) $yStr < (int) $woY) {
                                $sal[$yStr] = null;
                                $rec[$yStr] = null;
                                continue;
                            }
                            $mv = collect($d->movement)->firstWhere('year', $yStr);
                            // Jika ada snapshot tahun ini: pakai outstanding. Jika tidak ada (debitur lunas/hilang): 0.
                            $curOs = $mv ? (float) $mv['os'] : 0.0;
                            $sal[$yStr] = $curOs;
                            $rec[$yStr] = max(0.0, $prevOs - $curOs);
                            $prevOs = $curOs;
                        }
                        $cohortDebs[] = (object) [
                            'loan_number' => $d->loan_number,
                            'group_key' => $d->group_key,
                            'wo_nominal' => $d->wo_nominal,
                            'sal' => $sal,
                            'rec' => $rec,
                            'total_recovery' => (float) $d->recovery,
                            'recovery_rate' => $d->wo_nominal > 0 ? (float) $d->recovery / $d->wo_nominal : 0.0,
                        ];
                    }

                    // Baris combine per tahun WO.
                    $combBal = $debList->sum('wo_nominal');
                    $combSal = [];
                    $combRec = [];
                    foreach ($allYears as $yStr) {
                        $salSum = 0.0;
                        $recSum = 0.0;
                        $hasVal = false;
                        foreach ($cohortDebs as $cd) {
                            if ($cd->sal[$yStr] !== null) {
                                $salSum += $cd->sal[$yStr];
                                $recSum += $cd->rec[$yStr];
                                $hasVal = true;
                            }
                        }
                        $combSal[$yStr] = $hasVal ? $salSum : null;
                        $combRec[$yStr] = $hasVal ? $recSum : null;
                    }
                    $combTotalRec = $debList->sum('recovery');

                    $erCohorts->push((object) [
                        'year' => $woY,
                        'debiturs' => $cohortDebs,
                        'combine_balance' => $combBal,
                        'combine_sal' => $combSal,
                        'combine_rec' => $combRec,
                        'combine_total_rec' => $combTotalRec,
                        'combine_rate' => $combBal > 0 ? $combTotalRec / $combBal : 0.0,
                    ]);
                }

                $totBal = $debtorRows->sum('wo_nominal');
                $totRec = $debtorRows->sum('recovery');
                $totSalYears = [];
                $totRecYears = [];
                foreach ($allYears as $yStr) {
                    $s = 0.0; $r = 0.0;
                    foreach ($erCohorts as $c) {
                        $s += (float) ($c->combine_sal[$yStr] ?? 0);
                        $r += (float) ($c->combine_rec[$yStr] ?? 0);
                    }
                    $totSalYears[$yStr] = $s;
                    $totRecYears[$yStr] = $r;
                }

                $erTotalCombine = (object) [
                    'years' => $allYears,
                    'total_balance' => $totBal,
                    'sal_years' => $totSalYears,
                    'rec_years' => $totRecYears,
                    'total_recovery' => $totRec,
                    'recovery_rate' => $totBal > 0 ? $totRec / $totBal : 0.0,
                    'lgd' => $totBal > 0 ? max(0.0, 1.0 - ($totRec / $totBal)) : 0.0,
                ];
            }

            // Ringkasan per segmentasi (group_key) untuk tampilan tabel segmentasi
            $groupSummary = $debtorRows->groupBy('group_key')->map(function ($grp) {
                $totalExp = $grp->sum('exposure');
                $totalRec = $grp->sum('recovery');
                $recoveryRate = $totalExp > 0 ? ($totalRec / $totalExp) : 0;
                $lgdRate = 1 - $recoveryRate;
                return (object) [
                    'group_key' => $grp->first()->group_key,
                    'total_wo' => $totalExp,
                    'total_recovery' => $totalRec,
                    'recovery_rate' => $recoveryRate,
                    'lgd_rate' => $lgdRate,
                ];
            })->sortByDesc('total_wo')->values();

            // Ringkasan Weighted LGD (kombinasi ER + CS) seperti kertas kerja B4 baris 24-26.
            // Filter per group_key jika ada seleksi grup.
            $selectedGroup = $request->query('group_key');
            $erQuery = DB::table('stage_lgd_results')
                ->where('assessment_period_id', $period->id)
                ->where('method', 'ER');
            if ($selectedGroup) {
                $erQuery->where('group_key', $selectedGroup);
            }
            // ER menyimpan eksposur di total_write_off & total_recovery,
            // sedangkan CS memakai total_exposure_wo & total_shortfall.
            $erResult = $erQuery->selectRaw('COALESCE(SUM(total_write_off), 0) as exp, COALESCE(SUM(total_recovery), 0) as recovery')->first();

            $csQuery = DB::table('stage_lgd_results')
                ->where('assessment_period_id', $period->id)
                ->where('method', 'CS');
            if ($selectedGroup) {
                $csQuery->where('group_key', $selectedGroup);
            }
            $csResult = $csQuery->selectRaw('COALESCE(SUM(total_exposure_wo), 0) as exp, COALESCE(SUM(total_shortfall), 0) as shortfall')->first();

            $erExp = (float) ($erResult->exp ?? 0);
            $erRecovery = (float) ($erResult->recovery ?? 0);
            $erLoss = max(0.0, $erExp - $erRecovery);
            $csExp = (float) ($csResult->exp ?? 0);
            $csLoss = (float) ($csResult->shortfall ?? 0);
            $csRecovery = $csExp - $csLoss;
            $combExp = $erExp + $csExp;
            $combLoss = $erLoss + $csLoss;

            $weightedSummary = (object) [
                'er_exp' => $erExp,
                'er_loss' => $erLoss,
                'er_recovery' => $erRecovery,
                'er_lgd' => $erExp > 0 ? $erLoss / $erExp : 0.0,
                'cs_exp' => $csExp,
                'cs_loss' => $csLoss,
                'cs_recovery' => $csRecovery,
                'cs_lgd' => $csExp > 0 ? $csLoss / $csExp : 0.0,
                'total_exp' => $combExp,
                'total_loss' => $combLoss,
                'weighted_lgd' => $combExp > 0 ? $combLoss / $combExp : 0.0,
            ];

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
            'erCohorts' => $erCohorts ?? collect(),
            'erTotalCombine' => $erTotalCombine ?? null,
            'groupSummary' => $groupSummary ?? collect(),
            'weightedSummary' => $weightedSummary ?? null,
            'groups' => $groups,
            'selectedGroup' => $request->query('group_key'),
            'emptyNotice' => null,
            'backRoute' => $method === 'ER' ? 'calculation.module.lgd_er' : 'calculation.module.lgd_cs',
            'histM' => $histM,
            'saleCostPct' => $saleCostPct,
        ]);
    }
}
