@extends('layouts.app')

@section('title', 'PD Net Flow')
@section('page-title', '3. PD Net Flow')

@section('content')
@php
    $runRoute    = route('calculation.module.pd_netflow.run');
    $exportRoute = route('calculation.export.module.period', ['module' => 'pd-netflow', 'assessmentPeriod' => $period->id]);
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">PD Net Flow per Grup Segmentasi</h2>
            <p class="text-sm text-gray-600 mt-1">Window historis: {{ $config['historical_months'] ?? 24 }} bulan · Method: <span class="font-semibold">NET_FLOW</span></p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ $runRoute }}">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent text-white text-sm font-semibold rounded-lg hover:bg-blue-700 shadow-sm transition-colors duration-200">▶ Hitung PD Net Flow</button>
            </form>
            <a href="{{ route('calculation.module.pd_netflow.detail', ['assessmentPeriod' => $period->id]) }}" class="inline-flex items-center px-4 py-2 bg-indigo-50 border border-indigo-200 text-sm font-semibold text-indigo-700 rounded-lg hover:bg-indigo-100 hover:border-indigo-300 shadow-sm transition-colors duration-200">
                <svg class="w-4 h-4 mr-1.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                Semua Matriks Segmentasi
            </a>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-semibold text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm transition-colors duration-200">
                <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                Export XLSX
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Groups</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $results['count'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Average PD</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) ($results['avg_pd'] ?? 0), 4, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Method</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">NET_FLOW</p>
        </div>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h3 class="text-sm font-bold text-gray-900">Hasil PD per Group</h3>
        <a href="{{ route('calculation.module.pd_netflow.detail', ['assessmentPeriod' => $period->id]) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
            Lihat Semua Matriks Segmentasi &rarr;
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Group Key</th>
                    <th class="px-3 py-2 text-right">PD</th>
                    <th class="px-3 py-2 text-right">Periods</th>
                    <th class="px-3 py-2 text-left">Detail</th>
                    <th class="px-3 py-2 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($results['rows'] ?? [] as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->pd_calculated, 6, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $r->periods_analyzed }}</td>
                        <td class="px-3 py-2 text-gray-600 font-mono">{{ Str::limit((string) $r->detail, 80) }}</td>
                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('calculation.module.pd_netflow.detail', ['assessmentPeriod' => $period->id, 'group_key' => $r->group_key]) }}" class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded text-xs font-semibold">
                                Lihat Matriks
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada hasil PD.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection