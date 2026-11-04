@extends('layouts.app')

@section('title', 'Laporan Matriks Risiko - CKPN Calculator')

@section('page-title', 'Laporan Matriks Risiko')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $assessment->period_name }}</h2>
                <p class="text-gray-600 mt-1">Tanggal Assessment: {{ $assessment->assessment_date->format('d M Y') }}</p>
            </div>
            <a href="{{ route('reporting.export-report', ['type' => 'risk-matrix', 'assessment_id' => $assessment->id]) }}" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Ekspor
            </a>
        </div>
    </div>

    <!-- Risk Matrix Grid -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-6">Matriks Risiko PD &times; LGD (interval 10%)</h3>

        @php
            $matrixTotal = array_sum($matrix ?? []);
        @endphp

        @if ($matrixTotal <= 0)
            <div class="py-12 text-center max-w-md mx-auto">
                <svg class="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <p class="text-sm font-semibold text-gray-900">Belum ada data eksposur dalam matriks risiko</p>
                <p class="text-xs text-gray-500 mt-1">Hasil kalkulasi PD dan LGD belum tersedia atau belum dipetakan ke assessment ini.</p>
                <a href="{{ route('calculation.module.config') }}" class="inline-flex items-center gap-1 mt-3 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700">Buka Modul Kalkulasi</a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                <thead>
                    <tr>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold">PD \ LGD</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">0-10%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">10-20%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">20-30%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">30-40%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">40-50%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">50-60%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">60-70%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">70-80%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">80-90%</th>
                        <th class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold text-center">90-100%</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $pdBuckets = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90];
                        $lgdBuckets = [0, 10, 20, 30, 40, 50, 60, 70, 80, 90];
                    @endphp
                    @foreach ($pdBuckets as $pd)
                        <tr>
                            <td class="border border-gray-300 px-4 py-2 bg-gray-100 font-semibold">{{ $pd }}-{{ $pd + 10 }}%</td>
                            @foreach ($lgdBuckets as $lgd)
                                @php
                                    $key = "{$pd}-{$lgd}";
                                    $value = $matrix[$key] ?? 0;
                                    $intensity = min(100, ($value / 100_000_000) * 100);
                                    $bgColor = $intensity < 20 ? 'bg-emerald-50' : ($intensity < 40 ? 'bg-amber-50' : ($intensity < 60 ? 'bg-red-50' : 'bg-red-100'));
                                @endphp
                                <td class="border border-gray-300 px-4 py-2 text-center text-xs {{ $bgColor }}">
                                    @if ($value > 0)
                                        <span class="font-semibold text-gray-800">{{ number_format($value / 1_000_000, 0) }}M</span>
                                    @else
                                        <span class="text-gray-400">-</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex gap-4 items-center">
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-emerald-100 border border-gray-300 rounded"></div>
                <span class="text-sm">Risiko Rendah</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-amber-100 border border-gray-300 rounded"></div>
                <span class="text-sm">Risiko Sedang</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-100 border border-gray-300 rounded"></div>
                <span class="text-sm">Risiko Tinggi</span>
            </div>
            <div class="flex items-center gap-2">
                <div class="w-4 h-4 bg-red-200 border border-gray-300 rounded"></div>
                <span class="text-sm">Risiko Sangat Tinggi</span>
            </div>
        </div>
    </div>

    <!-- Summary Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Average PD</p>
            <p class="text-2xl font-mono font-bold text-gray-900">{{ number_format(($cached['pd']['average_pd'] ?? 0) * 100, 2) }}%</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Average LGD</p>
            <p class="text-2xl font-mono font-bold text-gray-900">{{ number_format(($cached['lgd']['average_lgd'] ?? 0) * 100, 2) }}%</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Total EAD</p>
            <p class="text-2xl font-mono font-bold text-gray-900">{{ number_format(($cached['ead']['total_ead'] ?? 0) / 1_000_000_000, 2) }}B</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Total CKPN</p>
            <p class="text-2xl font-mono font-bold text-gray-900">{{ number_format((($cached['individual_ckpn']['total_ckpn_individual'] ?? 0) + ($cached['collective_ckpn']['total_ckpn_collective'] ?? 0)) / 1_000_000_000, 2) }}B</p>
        </div>
    </div>

    <!-- Back Button -->
    <div class="flex gap-3">
        <a href="{{ route('reporting.ckpn-summary') }}" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Kembali</a>
    </div>
</div>
@endsection
