@extends('layouts.app')

@section('title', 'Klasifikasi & Segmentasi')
@section('page-title', '2. Klasifikasi & Segmentasi')

@section('content')
@php
    $runRoute   = route('calculation.module.classification.run');
    $exportRoute = route('calculation.export.module.period', ['module' => 'classification', 'assessmentPeriod' => $period->id]);
    $dimList = isset($config['segment_dimensions']) ? explode('|', str_replace(',', '|', $config['segment_dimensions'])) : ['OFFICE'];
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">Tetapkan daftar debitur Individual & Kolektif + Segmentasi Kolektif</h2>
            <p class="text-sm text-gray-600 mt-1">Periode: {{ $period->assessment_date?->format('Y-m-d') ?? '-' }} · Segmen aktif: <span class="font-semibold">{{ implode(' | ', $dimList) }}</span></p>
            <p class="text-xs text-gray-500 mt-1">Kriteria Individual: <span class="font-semibold">NPL (kualitas/kolektibilitas {{ implode(', ', $config['npl_ratings'] ?? [3,4,5]) }})</span> diambil <span class="font-semibold">TOP {{ $config['individual_top_n'] ?? 20 }}</span> outstanding terbesar.</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ $runRoute }}" data-progress data-confirm-title="Konfirmasi Klasifikasi & Segmentasi" data-confirm-message="Proses akan mengklasifikasi ulang seluruh debitur periode ini menjadi Individual/Kolektif beserta segmentasinya." data-confirm-type="warning" data-confirm-button="Ya, Jalankan" data-loading-message="Menjalankan klasifikasi & segmentasi…">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                @foreach ($dimList as $d)
                    <input type="hidden" name="segment_dimensions[]" value="{{ $d }}">
                @endforeach
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">Jalankan Klasifikasi</button>
            </form>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Ekspor XLSX</a>
        </div>
    </div>

    <!-- KPI Metric Cards (Visual Hierarchy: Total EAD as Primary Anchor) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="md:col-span-2 rounded-xl border border-gray-200 bg-gradient-to-br from-slate-50 to-white p-5 shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700 uppercase">Eksposur Portofolio</span>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mt-1">Total EAD Terklasifikasi</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-3 flex items-baseline justify-between">
                <p class="text-2xl font-bold text-gray-900 font-mono tracking-tight">Rp {{ number_format($counts['TOTAL_EAD'] ?? 0, 0, ',', '.') }}</p>
                <span class="text-xs text-gray-500 font-mono">{{ number_format(($counts['INDIVIDUAL'] ?? 0) + ($counts['COLLECTIVE'] ?? 0), 0, ',', '.') }} rekening</span>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Individual</p>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
                <p class="text-xl font-bold text-gray-900 mt-2 font-mono">{{ number_format($counts['INDIVIDUAL'] ?? 0, 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">debitur</span></p>
            </div>
            <p class="text-xs text-gray-500 mt-3 pt-2 border-t border-gray-100 font-mono">EAD: Rp {{ number_format($counts['INDIVIDUAL_EAD'] ?? 0, 0, ',', '.') }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Kolektif</p>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                </div>
                <p class="text-xl font-bold text-gray-900 mt-2 font-mono">{{ number_format($counts['COLLECTIVE'] ?? 0, 0, ',', '.') }} <span class="text-xs font-normal text-gray-500">rekening</span></p>
            </div>
            <p class="text-xs text-gray-500 mt-3 pt-2 border-t border-gray-100 font-mono">EAD: Rp {{ number_format($counts['COLLECTIVE_EAD'] ?? 0, 0, ',', '.') }}</p>
        </div>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900">Preview Klasifikasi (50 baris teratas)</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Loan</th>
                    <th class="px-3 py-2 text-left">Classification</th>
                    <th class="px-3 py-2 text-left">Kantor</th>
                    <th class="px-3 py-2 text-left">Akad</th>
                    <th class="px-3 py-2 text-left">Usage</th>
                    <th class="px-3 py-2 text-right">EAD</th>
                    <th class="px-3 py-2 text-left">Reason</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($classifications as $c)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $c->loan_number }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ $c->classification === 'INDIVIDUAL' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $c->classification }}</span>
                        </td>
                        <td class="px-3 py-2">{{ $c->office_code }}</td>
                        <td class="px-3 py-2">{{ $c->akad_code }}</td>
                        <td class="px-3 py-2">{{ $c->usage_code }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $c->ead_amount, 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $c->reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada hasil. Klik "Jalankan Klasifikasi".</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection