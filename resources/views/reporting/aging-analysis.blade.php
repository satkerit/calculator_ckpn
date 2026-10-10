@extends('layouts.app')

@section('title', 'Aging Analysis Report - CKPN Calculator')

@section('page-title', 'Aging Analysis Report')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-start">
            <div>
                <h2 class="text-2xl font-bold text-gray-800">{{ $assessment->period_name }}</h2>
                <p class="text-gray-600 mt-1">Assessment Date: {{ $assessment->assessment_date->format('d M Y') }}</p>
            </div>
            <a href="{{ route('reporting.export-report', ['type' => 'aging-analysis', 'assessment_id' => $assessment->id]) }}" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export
            </a>
        </div>
    </div>

    <!-- Aging Buckets -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-6">Days Past Due Distribution</h3>

        <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-8">
            @php
                $total = array_sum($agingBuckets);
            @endphp
            @foreach ($agingBuckets as $bucket => $amount)
                @php
                    $percentage = $total > 0 ? ($amount / $total) * 100 : 0;
                    $color = $bucket === '0-30' ? 'bg-green-50 border-green-500' : 
                             ($bucket === '31-60' ? 'bg-yellow-50 border-yellow-500' :
                             ($bucket === '61-90' ? 'bg-orange-50 border-orange-500' : 'bg-red-50 border-red-500'));
                @endphp
                <div class="p-4 rounded-lg border-l-4 {{ $color }}">
                    <p class="text-xs uppercase font-semibold text-gray-600 mb-1">{{ $bucket }} Days</p>
                    <p class="text-2xl font-bold text-gray-900">{{ number_format($amount / 1_000_000_000, 1) }}B</p>
                    <p class="text-xs text-gray-500 mt-1">{{ number_format($percentage, 1) }}%</p>
                </div>
            @endforeach
        </div>

        <!-- Aging Bar Chart -->
        <div class="mb-6">
            <p class="text-sm font-semibold text-gray-700 mb-3">Distribution</p>
            <div class="flex h-8 rounded-lg overflow-hidden gap-1 bg-gray-100">
                @foreach ($agingBuckets as $bucket => $amount)
                    @php
                        $percentage = $total > 0 ? ($amount / $total) * 100 : 0;
                        $color = $bucket === '0-30' ? 'bg-green-500' : 
                                 ($bucket === '31-60' ? 'bg-yellow-500' :
                                 ($bucket === '61-90' ? 'bg-orange-500' : 'bg-red-500'));
                    @endphp
                    @if ($percentage > 0)
                        <div class="flex items-center justify-center {{ $color }}" style="width: {{ $percentage }}%">
                            <span class="text-xs font-semibold text-white">{{ number_format($percentage, 0) }}%</span>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <!-- Summary -->
        <div class="p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
            <p class="text-sm font-semibold text-gray-700">Total CKPN in Aging Analysis</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ number_format($total / 1_000_000_000, 2) }}B</p>
        </div>
    </div>

    <!-- Collateral Coverage -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-6">Collateral Coverage</h3>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Full Coverage -->
            <div class="p-6 bg-green-50 rounded-lg border border-green-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-semibold text-gray-800">Full Coverage</p>
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m7 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-3xl font-bold text-green-600">{{ number_format($collateralCoverage['full'] / 1_000_000_000, 2) }}B</p>
                <p class="text-sm text-gray-600 mt-2">{{ number_format(($collateralCoverage['full'] / ($collateralCoverage['full'] + $collateralCoverage['partial'] + $collateralCoverage['none'])) * 100, 1) }}% of total</p>
            </div>

            <!-- Partial Coverage -->
            <div class="p-6 bg-yellow-50 rounded-lg border border-yellow-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-semibold text-gray-800">Partial Coverage</p>
                    <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4v2m0 5v.01M12 3a9 9 0 110 18 9 9 0 010-18z"/></svg>
                </div>
                <p class="text-3xl font-bold text-yellow-600">{{ number_format($collateralCoverage['partial'] / 1_000_000_000, 2) }}B</p>
                <p class="text-sm text-gray-600 mt-2">{{ number_format(($collateralCoverage['partial'] / ($collateralCoverage['full'] + $collateralCoverage['partial'] + $collateralCoverage['none'])) * 100, 1) }}% of total</p>
            </div>

            <!-- No Coverage -->
            <div class="p-6 bg-red-50 rounded-lg border border-red-200">
                <div class="flex items-center justify-between mb-4">
                    <p class="font-semibold text-gray-800">No Coverage</p>
                    <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <p class="text-3xl font-bold text-red-600">{{ number_format($collateralCoverage['none'] / 1_000_000_000, 2) }}B</p>
                <p class="text-sm text-gray-600 mt-2">{{ number_format(($collateralCoverage['none'] / ($collateralCoverage['full'] + $collateralCoverage['partial'] + $collateralCoverage['none'])) * 100, 1) }}% of total</p>
            </div>
        </div>
    </div>

    <!-- Risk Assessment -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-500">
            <p class="text-gray-600 text-sm">Average PD</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format(($cached['pd']['average_pd'] ?? 0) * 100, 2) }}%</p>
        </div>
        <div class="bg-purple-50 rounded-lg p-4 border-l-4 border-purple-500">
            <p class="text-gray-600 text-sm">Average LGD</p>
            <p class="text-2xl font-bold text-purple-600">{{ number_format(($cached['lgd']['average_lgd'] ?? 0) * 100, 2) }}%</p>
        </div>
        <div class="bg-green-50 rounded-lg p-4 border-l-4 border-green-500">
            <p class="text-gray-600 text-sm">Individual CKPN</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format(($cached['individual_ckpn']['total_ckpn_individual'] ?? 0) / 1_000_000_000, 2) }}B</p>
        </div>
        <div class="bg-orange-50 rounded-lg p-4 border-l-4 border-orange-500">
            <p class="text-gray-600 text-sm">Collective CKPN</p>
            <p class="text-2xl font-bold text-orange-600">{{ number_format(($cached['collective_ckpn']['total_ckpn_collective'] ?? 0) / 1_000_000_000, 2) }}B</p>
        </div>
    </div>

    <!-- Back Button -->
    <div class="flex gap-3">
        <a href="{{ route('reporting.ckpn-summary') }}" class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">← Back</a>
    </div>
</div>
@endsection
