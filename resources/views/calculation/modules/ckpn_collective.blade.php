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
        <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Ekspor XLSX</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <!-- Hero: Total CKPN Kolektif -->
        <div class="col-span-1 md:col-span-2 rounded-xl border-2 border-amber-300/80 p-5 bg-gradient-to-br from-amber-50/60 to-white shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 uppercase">Metrik Utama</span>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mt-1">Total CKPN Kolektif</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-3xl font-extrabold text-gray-900 tracking-tight font-mono">Rp {{ number_format($summary['total_ckpn'] ?? 0, 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Akumulasi estimasi cadangan kerugian portofolio kolektif</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total EAD</p>
            <p class="text-xl font-bold text-gray-900 mt-2 font-mono">Rp {{ number_format($summary['total_ead'] ?? 0, 0, ',', '.') }}</p>
            <p class="text-xs text-gray-500 mt-1">Eksposur saat default</p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Debitur &amp; Segmen</p>
            <p class="text-xl font-bold text-gray-900 mt-2 font-mono">{{ number_format($summary['count'] ?? 0, 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">deb.</span></p>
            <p class="text-xs text-gray-500 mt-1">{{ number_format($summary['groups'] ?? 0, 0, ',', '.') }} grup segmentasi aktif</p>
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
                    <tr class="hover:bg-gray-50/70">
                        <td class="px-3 py-2 font-mono text-gray-900">{{ $r->loan_number }}</td>
                        <td class="px-3 py-2 font-mono text-gray-900">{{ $r->group_key }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center gap-1">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-100 text-gray-700 font-semibold text-[11px]">{{ (int) $r->dim_no }}</span>
                                <span class="text-gray-700">{{ $r->dim_label }}</span>
                            </span>
                        </td>
                        <td class="px-3 py-2 text-right font-mono text-gray-900">Rp {{ number_format((float) $r->ead_amount, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-gray-900">{{ $r->pd !== null ? number_format((float) $r->pd * 100, 2, ',', '.').'%' : '-' }}</td>
                        <td class="px-3 py-2 text-right font-mono text-gray-900">{{ $r->lgd !== null ? number_format((float) $r->lgd * 100, 2, ',', '.').'%' : '-' }}</td>
                        <td class="px-3 py-2 text-right font-mono font-bold text-gray-900">Rp {{ number_format((float) $r->ckpn_debitur, 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada debitur kolektif untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
