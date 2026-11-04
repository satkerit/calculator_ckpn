@extends('layouts.app')

@section('title', 'Laporan Analisis Aging - CKPN Calculator')

@section('page-title', 'Laporan Analisis Aging')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $assessment->period_name }}</h2>
                <p class="text-gray-600 mt-1">Tanggal Assessment: {{ $assessment->assessment_date->format('d M Y') }}</p>
            </div>
            <a href="{{ route('reporting.export-report', ['type' => 'aging-analysis', 'assessment_id' => $assessment->id]) }}" class="bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Ekspor
            </a>
        </div>
    </div>

    <!-- Aging Buckets -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-6">Distribusi Hari Tunggakan</h3>

        @php
            $total = array_sum($agingBuckets);
        @endphp

        @if ($total <= 0)
            <div class="py-12 text-center max-w-md mx-auto">
                <svg class="w-10 h-10 mx-auto text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="text-sm font-semibold text-gray-900">Belum ada data aging untuk assessment ini</p>
                <p class="text-xs text-gray-500 mt-1">Data snapshot pembiayaan belum terisi atau seluruh baki debet bernilai nol.</p>
                <a href="{{ route('import.snapshot.form') }}" class="inline-flex items-center gap-1 mt-3 px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700">Impor Snapshot</a>
            </div>
        @else
            <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
                @foreach ($agingBuckets as $bucket => $amount)
                    @php
                        $percentage = $total > 0 ? ($amount / $total) * 100 : 0;
                    @endphp
                    <div class="p-4 rounded-lg bg-white border border-gray-200">
                        <p class="text-xs uppercase tracking-wide font-semibold text-gray-500 mb-1">{{ $bucket }} Hari</p>
                        <p class="text-2xl font-bold text-gray-900 font-mono">{{ number_format($amount / 1_000_000_000, 1) }}B</p>
                        <p class="text-xs text-gray-500 mt-1 font-mono">{{ number_format($percentage, 1) }}%</p>
                    </div>
                @endforeach
            </div>

            <!-- Aging Bar Chart -->
            <div class="mb-6">
                <p class="text-sm font-semibold text-gray-700 mb-3">Distribusi</p>
                <div class="flex h-8 rounded-lg overflow-hidden gap-1 bg-gray-100">
                    @foreach ($agingBuckets as $bucket => $amount)
                        @php
                            $percentage = $total > 0 ? ($amount / $total) * 100 : 0;
                            $color = $bucket === '0-30' ? 'bg-emerald-500' : 
                                     ($bucket === '31-60' ? 'bg-amber-500' :
                                     ($bucket === '61-90' ? 'bg-amber-600' : 'bg-red-600'));
                        @endphp
                        @if ($percentage > 0)
                            <div class="{{ $color }} flex items-center justify-center text-xs font-bold text-white transition-all" style="width: {{ $percentage }}%" title="{{ $bucket }} Hari: {{ number_format($percentage, 1) }}%">
                                @if ($percentage > 5)
                                    {{ number_format($percentage, 0) }}%
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Summary -->
        <div class="p-4 bg-white rounded-lg border border-gray-200">
            <p class="text-sm font-semibold text-gray-700">Total CKPN dalam Analisis Aging</p>
            <p class="text-2xl font-bold text-gray-900 mt-1 font-mono">{{ number_format($total / 1_000_000_000, 2) }}B</p>
        </div>
    </div>

    <!-- Collateral Coverage -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-6">Cakupan Agunan</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Full Coverage -->
            <div class="p-6 bg-white rounded-lg border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-semibold text-gray-800">Cakupan Penuh</p>
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-3xl font-bold text-gray-900 font-mono">{{ number_format($collateralCoverage['full'] / 1_000_000_000, 2) }}B</p>
                <p class="text-sm text-gray-600 mt-2 font-mono">{{ number_format(($collateralCoverage['full'] / max(1, $collateralCoverage['full'] + $collateralCoverage['partial'] + $collateralCoverage['none'])) * 100, 1) }}% dari total</p>
            </div>

            <!-- Partial Coverage -->
            <div class="p-6 bg-white rounded-lg border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-semibold text-gray-800">Cakupan Sebagian</p>
                    <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 5v.01M12 3a9 9 0 110 18 9 9 0 010-18z"/></svg>
                </div>
                <p class="text-3xl font-bold text-gray-900 font-mono">{{ number_format($collateralCoverage['partial'] / 1_000_000_000, 2) }}B</p>
                <p class="text-sm text-gray-600 mt-2 font-mono">{{ number_format(($collateralCoverage['partial'] / max(1, $collateralCoverage['full'] + $collateralCoverage['partial'] + $collateralCoverage['none'])) * 100, 1) }}% dari total</p>
            </div>

            <!-- No Coverage -->
            <div class="p-6 bg-white rounded-lg border border-gray-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-semibold text-gray-800">Tanpa Cakupan</p>
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-3xl font-bold text-gray-900 font-mono">{{ number_format($collateralCoverage['none'] / 1_000_000_000, 2) }}B</p>
                <p class="text-sm text-gray-600 mt-2 font-mono">{{ number_format(($collateralCoverage['none'] / max(1, $collateralCoverage['full'] + $collateralCoverage['partial'] + $collateralCoverage['none'])) * 100, 1) }}% dari total</p>
            </div>
        </div>
    </div>

    <!-- Risk Assessment -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Rata-rata PD</p>
            <p class="text-2xl font-bold text-gray-900 font-mono">{{ number_format(($cached['pd']['average_pd'] ?? 0) * 100, 2) }}%</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">Rata-rata LGD</p>
            <p class="text-2xl font-bold text-gray-900 font-mono">{{ number_format(($cached['lgd']['average_lgd'] ?? 0) * 100, 2) }}%</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">CKPN Individual</p>
            <p class="text-2xl font-bold text-gray-900 font-mono">{{ number_format(($cached['individual_ckpn']['total_ckpn_individual'] ?? 0) / 1_000_000_000, 2) }}B</p>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200">
            <p class="text-gray-500 text-sm">CKPN Kolektif</p>
            <p class="text-2xl font-bold text-gray-900 font-mono">{{ number_format(($cached['collective_ckpn']['total_ckpn_collective'] ?? 0) / 1_000_000_000, 2) }}B</p>
        </div>
    </div>

    <!-- Back Button -->
    <div class="flex gap-3">
        <a href="{{ route('reporting.ckpn-summary') }}" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Kembali</a>
    </div>
</div>
@endsection
