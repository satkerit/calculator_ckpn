@extends('layouts.app')

@section('title', "Detail LGD {$method}")
@section('page-title', "Detail LGD {$method} per Debitur")

@section('content')
@php
    $backUrl = route($backRoute, ['assessment_period_id' => $period->id]);
    $detailUrl = fn ($m) => route('calculation.module.lgd_detail', ['method' => $m, 'assessment_period_id' => $period->id]);
    $fmt = fn ($v) => is_null($v) ? '' : number_format((float) $v, 2, ',', '.');
    $pct = fn ($v) => number_format((float) $v * 100, 2, ',', '.').'%';
@endphp

<div class="space-y-5">
    {{-- Header --}}
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">Kertas Kerja LGD {{ $method }}</h2>
                <p class="text-sm text-gray-600 mt-1">
                    Periode: <span class="font-semibold">{{ $period->period_name ?? $period->id }}</span>
                    · Window: {{ $histM }} bulan
                    @if ($method === 'CS')
                        · Biaya penjualan: {{ number_format($saleCostPct * 100, 2) }}%
                    @endif
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    @if ($method === 'ER')
                        Data kredit dihapusbuku — penyelesaian cash. Populasi: WO tanpa agunan bernilai.
                    @else
                        Data kredit dihapusbuku dengan agunan / kol 5 aktif. Net Likuidasi = Nilai Agunan × (1 − {{ number_format($saleCostPct * 100, 2) }}%).
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <a href="{{ $detailUrl('ER') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg {{ $method === 'ER' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                    LGD ER
                </a>
                <a href="{{ $detailUrl('CS') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg {{ $method === 'CS' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                    LGD CS
                </a>
                <a href="{{ $backUrl }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">← Kembali</a>
            </div>
        </div>

        @if ($emptyNotice)
            <div class="mt-5 p-4 rounded-xl border border-amber-200 bg-amber-50 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-bold shrink-0">⚠</div>
                <p class="text-sm text-amber-900">{{ $emptyNotice }}</p>
            </div>
        @endif

        {{-- Tabel Segmentasi per Group Key --}}
        @if ($groupSummary && $groupSummary->count() > 0)
        <div class="mt-5">
            <h3 class="text-sm font-bold text-gray-700 mb-2">Ringkasan LGD {{ strtoupper($method) }} per Segmentasi</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border border-gray-300">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-3 py-2 border border-gray-300 text-left">Group Key</th>
                            <th class="px-3 py-2 border border-gray-300 text-right">Total WO</th>
                            <th class="px-3 py-2 border border-gray-300 text-right">Total Recovery</th>
                            <th class="px-3 py-2 border border-gray-300 text-right">Recovery Rate</th>
                            <th class="px-3 py-2 border border-gray-300 text-right">LGD {{ strtoupper($method) }} Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupSummary as $g)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 border border-gray-300 font-mono">{{ $g->group_key }}</td>
                            <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($g->total_wo) }}</td>
                            <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($g->total_recovery) }}</td>
                            <td class="px-3 py-2 border border-gray-300 text-right">{{ $pct($g->recovery_rate) }}</td>
                            <td class="px-3 py-2 border border-gray-300 text-right font-bold text-indigo-700">{{ $pct($g->lgd_rate) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Ringkasan LGD Weighted (kertas kerja B4 baris 24-26) --}}
        @if ($weightedSummary)
        <div class="mt-5 overflow-x-auto">
            <table class="min-w-full text-xs border border-gray-300">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 border border-gray-300 text-left" style="width:220px"></th>
                        @if ($method === 'ER')
                            <th class="px-3 py-2 border border-gray-300 text-right">LGD ER</th>
                        @else
                            <th class="px-3 py-2 border border-gray-300 text-right">LGD CS</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php
                        if ($method === 'ER') {
                            $exp = $weightedSummary->er_exp ?? 0;
                            $recovery = $weightedSummary->er_recovery ?? 0;
                            $recoveryRate = $exp > 0 ? ($recovery / $exp) : 0;
                            $lgdRate = 1 - $recoveryRate;
                        } else {
                            $exp = $weightedSummary->cs_exp ?? 0;
                            $recovery = $weightedSummary->cs_recovery ?? 0;
                            $recoveryRate = $exp > 0 ? ($recovery / $exp) : 0;
                            $lgdRate = 1 - $recoveryRate;
                        }
                    @endphp
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Total WO / Eksposur</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($exp) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Total Recovery</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($recovery) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Recovery Rate</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $pct($recoveryRate) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">LGD ER Rate</td>
                        <td class="px-3 py-2 border border-gray-300 text-right font-bold text-indigo-700">{{ $pct($lgdRate) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ===== LGD ER: Kertas Kerja B3 ===== --}}
    @if ($method === 'ER' && $erCohorts->isNotEmpty())
        @php $allYears = $erTotalCombine ? $erTotalCombine->years : []; @endphp

        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-gray-900">Kertas Kerja LGD Expected Recoveries</h3>
                <p class="text-xs text-gray-500">{{ $erCohorts->count() }} kohort tahun hapus buku · {{ $debtorRows->count() }} rekening</p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border border-gray-300">
                    <thead>
                        <tr class="bg-blue-50 text-gray-800">
                            <th rowspan="2" class="px-2 py-1.5 border border-gray-300" style="width:34px">No</th>
                            <th rowspan="2" class="px-2 py-1.5 border border-gray-300 text-left">Nama Nasabah / No Rekening</th>
                            <th rowspan="2" class="px-2 py-1.5 border border-gray-300" style="width:70px">Tahun<br>Hapus Buku</th>
                            <th rowspan="2" class="px-2 py-1.5 border border-gray-300 text-right" style="width:130px">Balance<br>Hapus Buku</th>
                            <th colspan="{{ count($allYears) }}" class="px-2 py-1.5 border border-gray-300">Saldo Outstanding per Tahun</th>
                            <th rowspan="2" class="px-2 py-1.5 border border-gray-300 text-right" style="width:120px">Jumlah<br>Recovery</th>
                            <th rowspan="2" class="px-2 py-1.5 border border-gray-300 text-right" style="width:85px">Recovery<br>Rate</th>
                        </tr>
                        <tr class="bg-blue-50 text-gray-800">
                            @foreach ($allYears as $i => $y)
                                <th class="px-2 py-1 border border-gray-300 text-right" style="width:110px">
                                    Tahun {{ $y }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php $no = 0; @endphp
                        @foreach ($erCohorts as $cohort)
                            {{-- Header kohort --}}
                            <tr class="bg-indigo-100">
                                <td colspan="{{ count($allYears) + 6 }}" class="px-2 py-1.5 border border-gray-300 font-bold text-indigo-900">
                                    Tahun hapus buku {{ $cohort->year }}
                                </td>
                            </tr>

                            @foreach ($cohort->debiturs as $d)
                                @php $no++; @endphp
                                {{-- Baris saldo OS --}}
                                <tr class="hover:bg-gray-50">
                                    <td class="px-2 py-1 border border-gray-300 text-center">{{ $no }}</td>
                                    <td class="px-2 py-1 border border-gray-300 font-medium">{{ $d->loan_number }}</td>
                                    <td class="px-2 py-1 border border-gray-300 text-center">{{ $cohort->year }}</td>
                                    <td class="px-2 py-1 border border-gray-300 text-right font-semibold">{{ $fmt($d->wo_nominal) }}</td>
                                    @foreach ($allYears as $y)
                                        <td class="px-2 py-1 border border-gray-300 text-right">{{ $fmt($d->sal[$y]) }}</td>
                                    @endforeach
                                    <td class="px-2 py-1 border border-gray-300 text-right"></td>
                                    <td class="px-2 py-1 border border-gray-300 text-right"></td>
                                </tr>
                                {{-- Baris recovery --}}
                                <tr class="bg-gray-50">
                                    <td class="px-2 py-1 border border-gray-300"></td>
                                    <td class="px-2 py-1 border border-gray-300 text-gray-500 italic pl-6">recovery</td>
                                    <td class="px-2 py-1 border border-gray-300"></td>
                                    <td class="px-2 py-1 border border-gray-300"></td>
                                    @foreach ($allYears as $y)
                                        <td class="px-2 py-1 border border-gray-300 text-right text-emerald-700">{{ $fmt($d->rec[$y]) }}</td>
                                    @endforeach
                                    <td class="px-2 py-1 border border-gray-300 text-right font-semibold text-emerald-700">{{ $fmt($d->total_recovery) }}</td>
                                    <td class="px-2 py-1 border border-gray-300 text-right font-semibold">{{ $pct($d->recovery_rate) }}</td>
                                </tr>
                            @endforeach

                            {{-- Baris combine kohort --}}
                            <tr class="bg-amber-50 font-semibold">
                                <td class="px-2 py-1 border border-gray-300"></td>
                                <td class="px-2 py-1 border border-gray-300 italic">combine</td>
                                <td class="px-2 py-1 border border-gray-300"></td>
                                <td class="px-2 py-1 border border-gray-300 text-right">{{ $fmt($cohort->combine_balance) }}</td>
                                @foreach ($allYears as $y)
                                    <td class="px-2 py-1 border border-gray-300 text-right">{{ $fmt($cohort->combine_sal[$y]) }}</td>
                                @endforeach
                                <td class="px-2 py-1 border border-gray-300 text-right">{{ $fmt($cohort->combine_total_rec) }}</td>
                                <td class="px-2 py-1 border border-gray-300 text-right">{{ $pct($cohort->combine_rate) }}</td>
                            </tr>
                            <tr class="bg-amber-50">
                                <td class="px-2 py-1 border border-gray-300"></td>
                                <td class="px-2 py-1 border border-gray-300 text-gray-500 italic pl-6">recovery</td>
                                <td class="px-2 py-1 border border-gray-300"></td>
                                <td class="px-2 py-1 border border-gray-300"></td>
                                @foreach ($allYears as $y)
                                    <td class="px-2 py-1 border border-gray-300 text-right text-emerald-700">{{ $fmt($cohort->combine_rec[$y]) }}</td>
                                @endforeach
                                <td class="px-2 py-1 border border-gray-300"></td>
                                <td class="px-2 py-1 border border-gray-300"></td>
                            </tr>
                            <tr><td colspan="{{ count($allYears) + 6 }}" class="h-2"></td></tr>
                        @endforeach

                        {{-- Total combine --}}
                        @if ($erTotalCombine)
                        <tr class="bg-indigo-200 font-bold text-indigo-900">
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300">Total combine</td>
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($erTotalCombine->total_balance) }}</td>
                            @foreach ($allYears as $y)
                                <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($erTotalCombine->sal_years[$y] ?? null) }}</td>
                            @endforeach
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($erTotalCombine->total_recovery) }}</td>
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $pct($erTotalCombine->recovery_rate) }}</td>
                        </tr>
                        <tr class="bg-indigo-200">
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300 text-gray-700 italic pl-6">recovery</td>
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            @foreach ($allYears as $y)
                                <td class="px-2 py-1.5 border border-gray-300 text-right text-emerald-800">{{ $fmt($erTotalCombine->rec_years[$y] ?? null) }}</td>
                            @endforeach
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Hasil LGD ER --}}
            @if ($erTotalCombine)
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs text-gray-500">Recovery Rate (weighted)</p>
                    <p class="text-lg font-bold text-gray-900">{{ $pct($erTotalCombine->recovery_rate) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs text-gray-500">LGD = 1 − Recovery Rate</p>
                    <p class="text-lg font-bold text-red-700">{{ $pct($erTotalCombine->lgd) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs text-gray-500">Formula</p>
                    <p class="text-xs text-gray-700 leading-relaxed mt-0.5">
                        LGD = (Σ Balance WO − Σ Recovery) / Σ Balance WO<br>
                        = ({{ $fmt($erTotalCombine->total_balance) }} − {{ $fmt($erTotalCombine->total_recovery) }}) / {{ $fmt($erTotalCombine->total_balance) }}
                    </p>
                </div>
            </div>
            @endif
        </div>
    @endif

    {{-- ===== LGD CS: Kertas Kerja B4 ===== --}}
    @if ($method === 'CS' && $debtorRows->isNotEmpty())
        <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-gray-900">Kertas Kerja LGD Collateral Shortfall</h3>
                <p class="text-xs text-gray-500">
                    {{ $debtorRows->where('is_write_off', true)->count() }} WO beragunan ·
                    {{ $debtorRows->where('is_write_off', false)->count() }} Kol 5 aktif
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-xs border border-gray-300">
                    <thead class="bg-blue-50 text-gray-800">
                        <tr>
                            <th class="px-2 py-1.5 border border-gray-300" style="width:34px">No</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-left">Nomor Rekening Kredit</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-center" style="width:80px">Status</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-right" style="width:130px">Baki Debet /<br>Outstanding</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-right" style="width:130px">Nilai Agunan<br>Dijaminkan</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-center" style="width:70px">Tahun<br>Hapus Buku</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-center" style="width:70px">Tahun<br>Eksekusi</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-right" style="width:150px">Nilai Agunan Setelah<br>Biaya (Net Likuidasi)</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-right" style="width:130px">Recovery Tunai<br>(sejak WO)</th>
                            <th class="px-2 py-1.5 border border-gray-300 text-right" style="width:130px">Shortfall<br>(Kerugian)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $no = 0; @endphp
                        @foreach ($debtorRows as $r)
                            @php $no++; @endphp
                            <tr class="hover:bg-gray-50 {{ $r->is_write_off ? '' : 'bg-yellow-50/40' }}">
                                <td class="px-2 py-1 border border-gray-300 text-center">{{ $no }}</td>
                                <td class="px-2 py-1 border border-gray-300 font-medium">{{ $r->loan_number }}</td>
                                <td class="px-2 py-1 border border-gray-300 text-center">
                                    @if ($r->is_write_off)
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-700">WO</span>
                                    @else
                                        <span class="inline-flex px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800">K5</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1 border border-gray-300 text-right font-semibold">{{ $fmt($r->exposure) }}</td>
                                <td class="px-2 py-1 border border-gray-300 text-right">{{ $fmt($r->liquidity_value) }}</td>
                                <td class="px-2 py-1 border border-gray-300 text-center">{{ $r->is_write_off ? $r->wo_year : '—' }}</td>
                                <td class="px-2 py-1 border border-gray-300 text-center text-gray-400">—</td>
                                <td class="px-2 py-1 border border-gray-300 text-right text-emerald-700">{{ $fmt($r->net_recovery) }}</td>
                                <td class="px-2 py-1 border border-gray-300 text-right text-blue-700">{{ $r->is_write_off ? $fmt($r->cash_recovery) : '—' }}</td>
                                <td class="px-2 py-1 border border-gray-300 text-right font-semibold {{ (float) $r->shortfall > 0 ? 'text-red-700' : 'text-gray-500' }}">{{ $fmt($r->shortfall) }}</td>
                            </tr>
                        @endforeach

                        {{-- Total --}}
                        @php
                            $totExposure = $debtorRows->sum('exposure');
                            $totLiq = $debtorRows->sum('liquidity_value');
                            $totNet = $debtorRows->sum('net_recovery');
                            $totCash = $debtorRows->sum('cash_recovery');
                            $totShort = $debtorRows->sum('shortfall');
                        @endphp
                        <tr class="bg-gray-100 font-bold">
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300">Total</td>
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($totExposure) }}</td>
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($totLiq) }}</td>
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300"></td>
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($totNet) }}</td>
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($totCash) }}</td>
                            <td class="px-2 py-1.5 border border-gray-300 text-right">{{ $fmt($totShort) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Hasil LGD CS --}}
            <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs text-gray-500">LGD = Total Shortfall / Total Baki Debet</p>
                    <p class="text-lg font-bold text-red-700">{{ $pct($totExposure > 0 ? $totShort / $totExposure : 0) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs text-gray-500">Total Shortfall (Kerugian)</p>
                    <p class="text-lg font-bold text-gray-900">{{ $fmt($totShort) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-3">
                    <p class="text-xs text-gray-500">Formula per debitur</p>
                    <p class="text-xs text-gray-700 leading-relaxed mt-0.5">
                        WO: Shortfall = Baki saat WO − (Net Likuidasi + Recovery Tunai)<br>
                        K5: Shortfall = Baki Debet − Net Likuidasi<br>
                        Net Likuidasi = Nilai Agunan × (1 − {{ number_format($saleCostPct * 100, 2) }}%)
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if ($debtorRows->isEmpty() && ! $emptyNotice)
        <div class="bg-white border border-gray-200 rounded-xl p-8 text-center text-gray-500 text-sm">
            Tidak ada rekening pada populasi LGD {{ $method }} untuk periode ini.
        </div>
    @endif
</div>
@endsection
