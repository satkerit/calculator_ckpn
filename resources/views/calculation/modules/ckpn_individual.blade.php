@extends('layouts.app')

@section('title', 'CKPN Individual')
@section('page-title', 'CKPN Individual')

@section('content')
@php
    $exportRoute = route('calculation.export.module.period', ['module' => 'detail-ckpn', 'assessmentPeriod' => $period->id]);
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">CKPN Individual</h2>
            <p class="text-sm text-gray-600 mt-1">Daftar debitur individual per rekening: EAD, nilai agunan, pengurangan (biaya jual), estimasi recovery, dan CKPN.</p>
        </div>
        <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Ekspor XLSX</a>
    </div>

    <!-- KPI Metric Cards (Visual Hierarchy: Total CKPN as Hero Metric) -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mt-6">
        <div class="col-span-2 rounded-xl border-2 border-emerald-300/80 bg-gradient-to-br from-emerald-50/60 to-white p-4 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 uppercase">Metrik Hasil</span>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mt-1">Total CKPN Individual</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <p class="text-2xl font-extrabold text-emerald-900 mt-2 font-mono">Rp {{ number_format($summary['total_ckpn'] ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Debitur</p>
            <p class="text-xl font-bold text-gray-900 mt-2 font-mono">{{ number_format($summary['count'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total EAD</p>
            <p class="text-xl font-bold text-gray-900 mt-2 font-mono">{{ number_format($summary['total_ead'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Nilai Agunan</p>
            <p class="text-xl font-bold text-gray-900 mt-2 font-mono">{{ number_format($summary['total_agunan'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Estimasi Recovery</p>
            <p class="text-xl font-bold text-gray-900 mt-2 font-mono">{{ number_format($summary['total_recovery'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">No. Akad</th>
                    <th class="px-3 py-2 text-right">EAD</th>
                    <th class="px-3 py-2 text-right">Nilai Agunan</th>
                    <th class="px-3 py-2 text-right">Pengurangan</th>
                    <th class="px-3 py-2 text-right">Estimasi Recovery</th>
                    <th class="px-3 py-2 text-right">CKPN</th>
                    <th class="px-3 py-2 text-left">Metode Recovery</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->loan_number }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->nilai_tercatat, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->liquidity_value, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->sale_cost, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->estimated_recovery, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold font-mono">{{ number_format((float) $r->ckpn_individual, 0, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ $r->recovery_method ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada CKPN individual untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
