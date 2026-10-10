@extends('layouts.app')

@section('content')
<div class="px-6 py-4">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">PD Net Flow Detail</h1>
            <p class="text-gray-600 mt-1">Period: <span class="font-semibold">Assessment {{ $assessmentPeriodId }}</span></p>
        </div>
        <a href="{{ route('calculation.module.pd_netflow', ['assessment_period_id' => $assessmentPeriodId]) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
            &larr; Kembali
        </a>
    </div>

    <!-- GROUP SELECTOR REMOVED -->
    <div class="mb-6 bg-white border border-gray-200 rounded-lg p-4 text-sm text-gray-700 font-medium">
        Menampilkan seluruh segmentasi secara langsung. Gulir ke bawah untuk melihat matriks PD Netflow per segmentasi.
    </div>

    @forelse ($allData as $gk => $data)
        <div class="mb-12 border-t-4 border-blue-600 pt-4 bg-gray-50/50 p-4 rounded-xl">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-xl font-extrabold text-blue-950">Group Segmentasi: <span class="text-blue-600 font-mono">{{ $gk }}</span></h2>
            </div>

            <!-- TABEL 1: MIN. BUCKET (Outstanding Balance per Bucket per Periode) -->
            <div class="mb-8 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                    <h3 class="text-lg font-bold text-gray-900">Tabel 1: Min. Bucket</h3>
                    <p class="text-xs text-gray-600 mt-1">Outstanding Balance per Bucket per Periode (Rupiah)</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead class="bg-blue-100">
                            <tr class="border border-gray-300">
                                <th class="px-3 py-2 text-left font-bold text-gray-900 border-r border-gray-300 w-24">No. Bucket</th>
                                <th class="px-3 py-2 text-left font-bold text-gray-900 border-r border-gray-300 w-32">Hari Tunggakan</th>
                                @foreach ($data['periods'] as $period)
                                    <th class="px-2 py-2 text-center font-bold text-gray-900 border-r border-gray-300 whitespace-nowrap w-28">
                                        {{ \Carbon\Carbon::parse($period)->format('M-y') }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-300">
                            @foreach ($data['buckets'] as $bucket)
                                <tr class="border-b border-gray-300 @if($bucket['bucket_no'] == 14) bg-yellow-50 @endif hover:bg-gray-50">
                                    <td class="px-3 py-2 text-center font-bold text-gray-900 border-r border-gray-300 bg-blue-50">
                                        {{ $bucket['bucket_no'] }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 border-r border-gray-300 font-mono text-xs bg-blue-50">
                                        {{ $bucket['hari_tunggakan'] }}
                                    </td>
                                    @foreach ($data['periods'] as $period)
                                        <td class="px-2 py-2 text-right text-gray-900 border-r border-gray-300 font-mono text-xs">
                                            {{ $service->formatValue($bucket['periods'][$period] ?? null, 'currency') }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TABEL 2: PERPINDAHAN BUCKET (Transition Rate) -->
            <div class="mb-8 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                    <h3 class="text-lg font-bold text-gray-900">Tabel 2: Perpindahan Bucket</h3>
                    <p class="text-xs text-gray-600 mt-1">Transition Rate antar Bucket (Persentase 0-100%)</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead class="bg-blue-100">
                            <tr class="border border-gray-300">
                                <th class="px-3 py-2 text-left font-bold text-gray-900 border-r border-gray-300 w-32">Perpindahan</th>
                                <th class="px-3 py-2 text-center font-bold text-gray-900 border-r border-gray-300 w-20">Flow Count</th>
                                @foreach ($data['transition_periods'] as $period)
                                    <th class="px-2 py-2 text-center font-bold text-gray-900 border-r border-gray-300 whitespace-nowrap w-24">
                                        {{ \Carbon\Carbon::parse($period)->format('M-y') }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-300">
                            @foreach ($data['transitions'] as $transition)
                                <tr class="border-b border-gray-300 hover:bg-gray-50">
                                    <td class="px-3 py-2 font-mono font-semibold text-gray-900 border-r border-gray-300 bg-blue-50">
                                        {{ $transition['label'] }}
                                    </td>
                                    <td class="px-3 py-2 text-center text-gray-700 border-r border-gray-300 font-mono">
                                        @php
                                            $counts = array_values($transition['flow_counts'] ?? []);
                                            $avgCount = !empty($counts) ? (int) array_sum($counts) / count($counts) : 0;
                                        @endphp
                                        {{ $avgCount > 0 ? number_format($avgCount, 0) : '-' }}
                                    </td>
                                    @foreach ($data['transition_periods'] as $period)
                                        <td class="px-2 py-2 text-right text-gray-900 border-r border-gray-300 font-mono">
                                            {{ $service->formatValue(($transition['periods'][$period] ?? 0) * 100, 'number') }}%
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TABEL 3: COMPOUND FLOW TO LOSS (PD Compound) -->
            <div class="mb-6 bg-white border border-gray-200 rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-blue-50">
                    <h3 class="text-lg font-bold text-gray-900">Tabel 3: Compound Flow to Loss</h3>
                    <p class="text-xs text-gray-600 mt-1">PD Compound per Source Bucket (Persentase 0-100%)</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-xs border-collapse">
                        <thead class="bg-blue-100">
                            <tr class="border border-gray-300">
                                <th class="px-3 py-2 text-left font-bold text-gray-900 border-r border-gray-300 w-20">Bucket</th>
                                <th class="px-3 py-2 text-left font-bold text-gray-900 border-r border-gray-300 w-32">Label</th>
                                @foreach ($data['transition_periods'] as $period)
                                    <th class="px-2 py-2 text-center font-bold text-gray-900 border-r border-gray-300 whitespace-nowrap w-24">
                                        {{ \Carbon\Carbon::parse($period)->format('M-y') }}
                                    </th>
                                @endforeach
                                <th class="px-3 py-2 text-center font-bold text-gray-900 border-r border-gray-300 whitespace-nowrap w-24 bg-amber-100">PD Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-300">
                            @foreach ($data['compounds'] as $compound)
                                <tr class="border-b border-gray-300 hover:bg-gray-50">
                                    <td class="px-3 py-2 text-center font-bold text-gray-900 border-r border-gray-300 bg-blue-50">
                                        {{ $compound['source_bucket'] }}
                                    </td>
                                    <td class="px-3 py-2 text-gray-700 border-r border-gray-300 font-mono text-xs bg-blue-50">
                                        {{ $compound['label'] }}
                                    </td>
                                    @foreach ($data['transition_periods'] as $period)
                                        <td class="px-2 py-2 text-right text-gray-900 border-r border-gray-300 font-mono">
                                            {{ $service->formatValue(($compound['periods'][$period] ?? 0) * 100, 'number') }}%
                                        </td>
                                    @endforeach
                                    <td class="px-2 py-2 text-right font-bold text-gray-900 border-r border-gray-300 font-mono bg-amber-50">
                                        {{ $service->formatValue($compound['pd_rate'] * 100, 'number') }}%
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-blue-50 border border-blue-300 rounded-lg p-6 text-center">
            <p class="text-blue-900 font-semibold">Data Belum Tersedia</p>
            <p class="text-blue-700 text-sm mt-1">Belum ada grup segmentasi yang tersedia pada periode penilaian ini.</p>
        </div>
    @endforelse
</div>
@endsection
