@extends('layouts.app')

@section('title', 'Dashboard - CKPN Calculator')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        <!-- Total Assessments -->
        <div class="bg-white rounded-xl border border-gray-200/80 p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Assessment</p>
                <div class="w-7 h-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-2xl font-bold text-gray-900 tracking-tight">{{ $assessmentCount }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    <span class="font-medium text-emerald-600">{{ ($assessmentByStatus->get('REVIEW')?->total ?? 0) + ($assessmentByStatus->get('APPROVED')?->total ?? 0) + ($assessmentByStatus->get('LOCKED')?->total ?? 0) }}</span> selesai
                </p>
            </div>
        </div>

        <!-- Total Snapshots -->
        <div class="bg-white rounded-xl border border-gray-200/80 p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Loan (Snapshot)</p>
                <div class="w-7 h-7 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($snapshotCount) }}</p>
                <p class="text-xs text-gray-500 mt-0.5">
                    <span class="font-medium text-purple-600">{{ number_format($classificationStats['with_usage_type']) }}</span> dg usage type
                </p>
            </div>
        </div>

        <!-- Total EAD -->
        <div class="bg-white rounded-xl border border-gray-200/80 p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total EAD</p>
                <div class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-2xl font-bold text-gray-900 tracking-tight">Rp {{ number_format($totalEad / 1_000_000_000, 2) }}M</p>
                <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $latestAssessment ? $latestAssessment->assessment_date->format('d M Y') : 'Belum ada data' }}</p>
            </div>
        </div>

        <!-- Total CKPN -->
        <div class="bg-white rounded-xl border border-gray-200/80 p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total CKPN</p>
                <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-2xl font-bold text-gray-900 tracking-tight">Rp {{ number_format($totalCkpn / 1_000_000_000, 2) }}M</p>
                <p class="text-xs text-gray-500 mt-0.5">Cadangan risiko</p>
            </div>
        </div>

        <!-- Active Users -->
        <div class="col-span-2 lg:col-span-1 bg-white rounded-xl border border-gray-200/80 p-4 sm:p-5 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Pengguna</p>
                <div class="w-7 h-7 rounded-lg bg-slate-50 text-slate-600 flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-2xl font-bold text-gray-900 tracking-tight">{{ $userCount }}</p>
                <p class="text-xs text-gray-500 mt-0.5">Akun terdaftar</p>
            </div>
        </div>
    </div>

    <!-- Quick Action Bar -->
    <div class="flex flex-wrap items-center gap-2 pt-1">
        <a href="{{ route('assessment.create') }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-colors shadow-sm">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Assessment Baru
        </a>
        <a href="{{ route('import.snapshot.form') }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs font-semibold rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            Import Snapshot
        </a>
        <a href="{{ route('calculation.module.config') }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs font-semibold rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            Hitung CKPN
        </a>
        <a href="{{ route('reporting.ckpn-summary') }}"
           class="inline-flex items-center gap-2 px-3 py-2 bg-white hover:bg-gray-50 text-gray-700 border border-gray-200 text-xs font-semibold rounded-lg transition-colors">
            <svg class="w-3.5 h-3.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Laporan
        </a>
    </div>

    <!-- Main Content: Recent Assessments + Snapshot Breakdown -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Recent Assessments (2 cols) -->
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200/80 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900">Assessment Terbaru</h2>
                    <p class="text-xs text-gray-400">Periode perhitungan terakhir</p>
                </div>
                <a href="{{ route('assessment.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">Lihat semua &rarr;</a>
            </div>

            <div class="divide-y divide-gray-100">
                @forelse ($recentAssessments as $assessment)
                    <div class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 transition-colors">
                        <div class="min-w-0 pr-4">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $assessment->period_name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $assessment->assessment_date->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-2.5 shrink-0">
                            @php
                            $badgeColor = match($assessment->status) {
                                'REVIEW', 'APPROVED', 'LOCKED' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'VALIDATING' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'CALCULATING' => 'bg-blue-50 text-blue-700 border-blue-200',
                                default => 'bg-gray-100 text-gray-600 border-gray-200',
                            };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium border {{ $badgeColor }}">
                                {{ $assessment->status }}
                            </span>
                            <a href="{{ route('assessment.show', $assessment) }}" class="p-1 rounded text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center text-xs text-gray-400">
                        Belum ada data assessment. <a href="{{ route('assessment.create') }}" class="text-blue-600 hover:underline">Buat baru</a>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Snapshot Distribution (1 col) -->
        <div class="bg-white rounded-xl border border-gray-200/80 p-5 flex flex-col justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Kelengkapan Data</h2>
                <p class="text-xs text-gray-400 mt-0.5">Kualitas atribut pembiayaan snapshot</p>

                <div class="mt-5 space-y-4">
                    @php
                        $total = $classificationStats['total'];
                        $withUsage = $classificationStats['with_usage_type'];
                        $pctUsage = $total > 0 ? round(($withUsage / $total) * 100, 1) : 0;
                    @endphp

                    <div>
                        <div class="flex justify-between items-center text-xs mb-1.5">
                            <span class="text-gray-600">Dengan Usage Type</span>
                            <span class="font-semibold text-gray-900">{{ number_format($withUsage) }} <span class="text-gray-400 font-normal">({{ $pctUsage }}%)</span></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="bg-blue-600 h-1.5 rounded-full" style="width: {{ $pctUsage }}%"></div>
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-center text-xs mb-1.5">
                            <span class="text-gray-600">Total Fasilitas Unik</span>
                            <span class="font-semibold text-gray-900">{{ number_format($total) }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-1.5">
                            <div class="bg-gray-400 h-1.5 rounded-full w-full"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pt-4 mt-6 border-t border-gray-100 text-center">
                <a href="{{ route('snapshot-history.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">
                    Buka Snapshot History &rarr;
                </a>
            </div>
        </div>

    </div>

    <!-- Recent Snapshots Table -->
    <div class="bg-white rounded-xl border border-gray-200/80 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">Snapshot Pembiayaan Terakhir</h2>
                <p class="text-xs text-gray-400">Sampel data staging pembiayaan</p>
            </div>
            <a href="{{ route('snapshot-history.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700">Lihat semua &rarr;</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-gray-50 border-b border-gray-100 text-gray-500 uppercase tracking-wider font-semibold">
                    <tr>
                        <th class="px-5 py-2.5">No. Rekening</th>
                        <th class="px-5 py-2.5">Produk</th>
                        <th class="px-5 py-2.5">Kantor</th>
                        <th class="px-5 py-2.5 text-right">Baki Debet (Rp)</th>
                        <th class="px-5 py-2.5 text-center">Periode</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($recentSnapshots as $snapshot)
                        <tr class="hover:bg-gray-50/75 transition-colors">
                            <td class="px-5 py-2.5 font-medium text-gray-900 font-mono">{{ $snapshot->loan_number }}</td>
                            <td class="px-5 py-2.5 text-gray-600">{{ $snapshot->product_code ?? '-' }}</td>
                            <td class="px-5 py-2.5 text-gray-600">{{ $snapshot->office?->office_name ?? $snapshot->office_code ?? '-' }}</td>
                            <td class="px-5 py-2.5 text-right font-medium text-gray-900 font-mono">{{ number_format($snapshot->outstanding_balance, 0, ',', '.') }}</td>
                            <td class="px-5 py-2.5 text-center text-gray-500">{{ $snapshot->periode_snapshot }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-gray-400">Belum ada data snapshot</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
