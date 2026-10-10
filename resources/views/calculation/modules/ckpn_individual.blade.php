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
            <p class="text-sm text-gray-600 mt-1">Hasil perhitungan CKPN per debitur individual (nilai tercatat &minus; estimasi recovery).</p>
        </div>
        <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Debitur</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['count'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Nilai Tercatat</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['total_tercatat'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Estimasi Recovery</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['total_recovery'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 p-4 bg-emerald-50">
            <p class="text-xs font-semibold text-emerald-700 uppercase">Total CKPN</p>
            <p class="text-xl font-bold text-emerald-900 mt-1">{{ number_format($summary['total_ckpn'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">No. Akad</th>
                    <th class="px-3 py-2 text-right">Nilai Tercatat</th>
                    <th class="px-3 py-2 text-right">Estimasi Recovery</th>
                    <th class="px-3 py-2 text-right">CKPN</th>
                    <th class="px-3 py-2 text-left">Metode Recovery</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->loan_number }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->nilai_tercatat, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->estimated_recovery, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->ckpn_individual, 0, ',', '.') }}</td>
                        <td class="px-3 py-2">{{ $r->recovery_method ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada CKPN individual untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
