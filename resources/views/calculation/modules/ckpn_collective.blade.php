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
            <p class="text-sm text-gray-600 mt-1">Hasil perhitungan CKPN per grup segmentasi (EAD &times; PD &times; LGD).</p>
        </div>
        <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Grup</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['count'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Anggota</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format($summary['members'] ?? 0, 0, ',', '.') }}</p>
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
                    <th class="px-3 py-2 text-left">Group</th>
                    <th class="px-3 py-2 text-left">Kantor</th>
                    <th class="px-3 py-2 text-left">Akad</th>
                    <th class="px-3 py-2 text-left">Penggunaan</th>
                    <th class="px-3 py-2 text-right">EAD</th>
                    <th class="px-3 py-2 text-right">PD</th>
                    <th class="px-3 py-2 text-right">LGD</th>
                    <th class="px-3 py-2 text-right">CKPN</th>
                    <th class="px-3 py-2 text-right">Anggota</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                        <td class="px-3 py-2">{{ $r->office_code ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $r->akad_code ?? '-' }}</td>
                        <td class="px-3 py-2">{{ $r->usage_code ?? '-' }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->total_ead, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->pd * 100, 2, ',', '.') }}%</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->lgd * 100, 2, ',', '.') }}%</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->ckpn_kolektif, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $r->member_count }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">Belum ada CKPN kolektif untuk periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
