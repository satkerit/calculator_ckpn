@extends('layouts.app')

@section('title', "Detail LGD {$method}")
@section('page-title', "Detail LGD {$method} per Debitur")

@section('content')
@php
    $backUrl = route($backRoute, ['assessment_period_id' => $period->id]);
    $detailUrl = fn ($m) => route('calculation.module.lgd_detail', ['method' => $m, 'assessment_period_id' => $period->id]);
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">Detail Write-Off per Debitur — LGD {{ $method }}</h2>
            <p class="text-sm text-gray-600 mt-1">
                Periode: <span class="font-semibold">{{ $period->period_name ?? $period->id }}</span>
                · Window: {{ $histM }} bulan
                @if ($method === 'CS')
                    · Sale cost: {{ number_format($saleCostPct * 100, 2) }}%
                @endif
            </p>
        </div>
        <div class="flex gap-2">
            <a href="{{ $detailUrl('ER') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg {{ $method === 'ER' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                Detail ER
            </a>
            <a href="{{ $detailUrl('CS') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg {{ $method === 'CS' ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                Detail CS
            </a>
            <a href="{{ $backUrl }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">← Kembali</a>
        </div>
    </div>

    @if ($emptyNotice)
        <div class="mt-6 p-4 rounded-xl border border-amber-200 bg-amber-50 flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-bold shrink-0">⚠</div>
            <p class="text-sm text-amber-900">{{ $emptyNotice }}</p>
        </div>
    @endif

    @if (! $emptyNotice)
        {{-- Filter grup segmentasi --}}
        <form method="GET" action="{{ route('calculation.module.lgd_detail', ['method' => $method]) }}" class="mt-6 flex items-end gap-3">
            <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
            <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Filter Grup Segmentasi</label>
                <select name="group_key" class="border border-gray-300 rounded-lg px-3 py-2 text-sm min-w-[260px]">
                    <option value="">— Semua Grup —</option>
                    @foreach ($groups as $gk)
                        <option value="{{ $gk }}" @selected($selectedGroup === $gk)>{{ $gk }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm font-semibold rounded-lg hover:bg-gray-900">Filter</button>
        </form>

        {{-- Ringkasan pergerakan OS per tahun (agregat lintas debitur) --}}
        <div class="mt-6">
            <h3 class="text-sm font-bold text-gray-900 mb-2">Pergerakan Penurunan OS per Tahun (agregat seluruh debitur WO)</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                @forelse ($yearlyRows as $y)
                    <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
                        <p class="text-xs font-semibold text-gray-600 uppercase">Tahun {{ $y->year }}</p>
                        <p class="text-lg font-bold text-gray-900 mt-1">{{ number_format((float) $y->total_os, 0, ',', '.') }}</p>
                        <p class="text-[11px] text-gray-500">OS outstanding akhir tahun</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Tidak ada data.</p>
                @endforelse
            </div>
        </div>

        {{-- Tabel detail debitur + pergerakan OS per tahun per debitur --}}
        <div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-bold text-gray-900">Detail per Debitur ({{ $debtorRows->count() }} rekening write-off)</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-xs">
                    <thead class="bg-gray-50 text-gray-600 uppercase sticky top-0">
                        <tr>
                            <th class="px-3 py-2 text-left">Loan</th>
                            <th class="px-3 py-2 text-left">Grup</th>
                            <th class="px-3 py-2 text-left">{{ $method === 'CS' ? 'Populasi' : 'Tgl WO' }}</th>
                            <th class="px-3 py-2 text-right">{{ $method === 'CS' ? 'Baki Debet' : 'OS Awal WO' }}</th>
                            <th class="px-3 py-2 text-center">Pergerakan OS per Tahun ({{ $method === 'CS' ? 'posisi historis' : 'awal WO' }} → posisi {{ $period->assessment_date->format('Y') }})</th>
                            @if ($method === 'CS')
                                <th class="px-3 py-2 text-right">Nilai Agunan</th>
                                <th class="px-3 py-2 text-right">Biaya Penjualan</th>
                                <th class="px-3 py-2 text-right">Net Recovery</th>
                                <th class="px-3 py-2 text-right">Shortfall</th>
                                <th class="px-3 py-2 text-right">Shortfall %</th>
                            @else
                                <th class="px-3 py-2 text-right">OS Saat Ini</th>
                                <th class="px-3 py-2 text-right">Recovery</th>
                                <th class="px-3 py-2 text-right">Recovery %</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($debtorRows as $r)
                            <tr class="align-top">
                                <td class="px-3 py-2 font-mono whitespace-nowrap">{{ $r->loan_number }}</td>
                                <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                                <td class="px-3 py-2 whitespace-nowrap">{{ $method === 'CS' ? 'Kualitas 5 / WO aktif' : $r->tglwo }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format((float) $r->wo_nominal, 0, ',', '.') }}</td>
                                <td class="px-3 py-2">
                                    {{-- Rantai pergerakan OS per tahun --}}
                                    <div class="flex items-center gap-1 flex-wrap">
                                        @foreach ($r->movement as $i => $m)
                                            @if ($i > 0)
                                                <span class="text-gray-400">→</span>
                                            @endif
                                            <span class="inline-flex flex-col items-center px-2 py-1 rounded-lg border {{ $m['os'] <= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-white border-gray-200' }}">
                                                <span class="text-[10px] text-gray-500 font-semibold">{{ $m['year'] }}</span>
                                                <span class="font-semibold {{ $m['os'] <= 0 ? 'text-emerald-700' : 'text-gray-900' }}">{{ number_format($m['os'], 0, ',', '.') }}</span>
                                                @if ($m['penurunan'] > 0)
                                                    <span class="text-[10px] text-red-600">▼ {{ number_format($m['penurunan'], 0, ',', '.') }}</span>
                                                @endif
                                            </span>
                                        @endforeach
                                    </div>
                                    <div class="mt-1 h-1.5 w-full max-w-[240px] bg-gray-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ min(100, $r->recovery_pct) }}%"></div>
                                    </div>
                                    <span class="text-[10px] text-gray-500">Penurunan kumulatif: {{ number_format($r->recovery_pct, 1, ',', '.') }}%</span>
                                </td>
                                @if ($method === 'CS')
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $r->liquidity_value, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right text-red-600">-{{ number_format((float) $r->sale_cost, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $r->net_recovery, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->shortfall, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($r->shortfall_pct, 1, ',', '.') }}%</td>
                                @else
                                    <td class="px-3 py-2 text-right">{{ number_format((float) $r->current_os, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right font-semibold text-emerald-700">{{ number_format((float) $r->recovery, 0, ',', '.') }}</td>
                                    <td class="px-3 py-2 text-right">{{ number_format($r->recovery_pct, 1, ',', '.') }}%</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="{{ $method === 'CS' ? 10 : 7 }}" class="px-3 py-6 text-center text-gray-500">Tidak ada rekening write-off pada window ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
@endsection
