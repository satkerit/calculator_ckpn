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
            <form method="POST" action="{{ $runRoute }}">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                @foreach ($dimList as $d)
                    <input type="hidden" name="segment_dimensions[]" value="{{ $d }}">
                @endforeach
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">▶ Jalankan Klasifikasi</button>
            </form>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
            <p class="text-xs font-semibold text-blue-700 uppercase">Total Count</p>
            <p class="text-2xl font-bold text-blue-900 mt-1">{{ ($counts['INDIVIDUAL'] ?? 0) + ($counts['COLLECTIVE'] ?? 0) }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-xs font-semibold text-emerald-700 uppercase">Individual</p>
            <p class="text-lg font-bold text-emerald-900 mt-1">{{ $counts['INDIVIDUAL'] ?? 0 }}</p>
            <p class="text-xs text-emerald-600 mt-2">EAD: {{ number_format($counts['INDIVIDUAL_EAD'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
            <p class="text-xs font-semibold text-amber-700 uppercase">Kolektif</p>
            <p class="text-lg font-bold text-amber-900 mt-1">{{ $counts['COLLECTIVE'] ?? 0 }}</p>
            <p class="text-xs text-amber-600 mt-2">EAD: {{ number_format($counts['COLLECTIVE_EAD'] ?? 0, 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-purple-200 bg-purple-50 p-4">
            <p class="text-xs font-semibold text-purple-700 uppercase">Total EAD</p>
            <p class="text-2xl font-bold text-purple-900 mt-1">{{ number_format($counts['TOTAL_EAD'] ?? 0, 0, ',', '.') }}</p>
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
                    <th class="px-3 py-2 text-left">Office</th>
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