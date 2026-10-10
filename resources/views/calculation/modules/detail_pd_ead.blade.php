@extends('layouts.app')

@section('title', 'Detail PD & EAD')
@section('page-title', '7. Detail PD & EAD')

@section('content')
@php
    $exportRoute = route('calculation.export.module.period', ['module' => 'detail-pd-ead', 'assessmentPeriod' => $period->id]);
@endphp

<div class="flex items-center justify-between mb-6">
    <p class="text-sm text-gray-600">Read-only view: distribusi EAD per bucket + PD per grup + loan detail.</p>
    <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <h3 class="text-sm font-bold text-gray-900 mb-3">Distribusi Klasifikasi</h3>
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr><th class="px-3 py-2 text-left">Tipe</th><th class="px-3 py-2 text-right">Count</th><th class="px-3 py-2 text-right">Total EAD</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach (['INDIVIDUAL' => 'bg-emerald-50', 'COLLECTIVE' => 'bg-amber-50'] as $k => $cls)
                    <tr class="{{ $cls }}">
                        <td class="px-3 py-2 font-semibold">{{ $k }}</td>
                        <td class="px-3 py-2 text-right">{{ $classificationCount[$k]['count'] ?? 0 }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($classificationCount[$k]['ead'] ?? 0), 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <h3 class="text-sm font-bold text-gray-900 mb-3">EAD per Bucket</h3>
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr><th class="px-3 py-2 text-left">Bucket</th><th class="px-3 py-2 text-right">EAD</th><th class="px-3 py-2 text-right">Members</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($eadBucket['rows'] ?? [] as $r)
                    <tr>
                        <td class="px-3 py-2">{{ $eadBucket['labels'][$r->bucket_no] ?? 'Bucket ' . $r->bucket_no }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->total_ead, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $r->total_members }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-3 py-6 text-center text-gray-500">Belum ada data bucket.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900">PD per Group</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr><th class="px-3 py-2 text-left">Group Key</th><th class="px-3 py-2 text-left">Method</th><th class="px-3 py-2 text-right">PD</th><th class="px-3 py-2 text-right">Periods</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($pdResults as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                        <td class="px-3 py-2">{{ $r->method }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->pd_calculated, 6, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ (int) $r->periods_analyzed }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">Belum ada PD.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900">Loan Detail (500 teratas)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Loan</th>
                    <th class="px-3 py-2 text-left">Class</th>
                    <th class="px-3 py-2 text-left">Office</th>
                    <th class="px-3 py-2 text-left">Akad</th>
                    <th class="px-3 py-2 text-left">Usage</th>
                    <th class="px-3 py-2 text-right">EAD</th>
                    <th class="px-3 py-2 text-right">PD</th>
                    <th class="px-3 py-2 text-left">Method</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($loans as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->loan_number }}</td>
                        <td class="px-3 py-2">{{ $r->classification }}</td>
                        <td class="px-3 py-2">{{ $r->office_code }}</td>
                        <td class="px-3 py-2">{{ $r->akad_code }}</td>
                        <td class="px-3 py-2">{{ $r->usage_code }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $r->ead_amount, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ $r->pd_calculated !== null ? number_format((float) $r->pd_calculated, 6, ',', '.') : '-' }}</td>
                        <td class="px-3 py-2">{{ $r->pd_method ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">Belum ada klasifikasi.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection