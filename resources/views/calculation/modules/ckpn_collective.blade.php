@extends('layouts.app')

@section('title', 'CKPN Kolektif')
@section('page-title', 'CKPN Kolektif')

@section('content')
@php
    $exportRoute = route('calculation.export.module.period', ['module' => 'detail-ckpn', 'assessmentPeriod' => $period->id]);
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">CKPN Kolektif</h2>
            <p class="text-sm text-gray-600 mt-1">Daftar debitur kolektif per rekening dengan rate PD &amp; LGD sesuai segmentasi dan bucket-nya (EAD &times; PD &times; LGD).</p>
        </div>
        <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Debitur</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['count'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Grup Segmentasi</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['groups'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Total EAD</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['total_ead'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 p-4 bg-amber-50">
            <p class="text-xs font-semibold text-amber-700 uppercase">Total CKPN</p>
            <p class="text-xl font-bold text-amber-900 mt-1">{{ number_format($summary['total_ckpn'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">No. Akad</th>
                    <th class="px-3 py-2 text-left">Segmen</th>
                    <th class="px-3 py-2 text-left">Bucket / Rating</th>
                    <th class="px-3 py-2 text-right">EAD</th>
                    <th class="px-3 py-2 text-right">PD</th>
                    <th class="px-3 py-2 text-right">LGD</th>
                    <th class="px-3 py-2 text-right">CKPN</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->loan_number }}</td>
                        <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 text-gray-700 font-semibold">{{ (int) $r->dim_no }}</span>
                                <span class="text-gray-600">{{ $r->dim_label }}</span>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->ead_amount, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ $r->pd !== null ? number_format((float) $r->pd * 100, 2, ',', '.').'%' : '-' }}</td>
                        <td class="px-3 py-2 text-right">{{ $r->lgd !== null ? number_format((float) $r->lgd * 100, 2, ',', '.').'%' : '-' }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->ckpn_debitur, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada debitur kolektif untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
