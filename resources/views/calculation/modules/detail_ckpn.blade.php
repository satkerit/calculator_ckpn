@extends('layouts.app')

@section('title', 'Detail CKPN & Finalisasi')
@section('page-title', '8. Detail CKPN & Finalisasi')

@section('content')
@php
    $finalizeRoute = route('calculation.module.detail_ckpn.finalize');
    $exportRoute   = route('calculation.export.module.period', ['module' => 'detail-ckpn', 'assessmentPeriod' => $period->id]);
    $staleLabels   = collect($stale ?? [])->map(fn ($t) => match ($t) {
        'PD'    => 'PD Net Flow / Migration',
        'LGD'   => 'LGD (ER & CS)',
        'CKPN'  => 'CKPN Kolektif',
        default => $t,
    })->implode(', ');
@endphp

@if (! empty($stale))
    {{-- Hasil tidak valid: konfigurasi berubah setelah PD/LGD/CKPN terakhir dihitung --}}
    <div class="mb-6 p-4 rounded-xl border border-red-200 bg-red-50 flex items-start gap-3">
        <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0"><svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></div>
        <div>
            <p class="text-sm font-semibold text-red-900">Sebagian hasil KURANG AKURAT / TIDAK VALID - konfigurasi berubah</p>
            <p class="text-xs text-red-700 mt-1">Konfigurasi (segmentasi / metode PD) berubah setelah perhitungan terakhir; <em>historical window</em> hanya membatalkan hasil PD Net Flow. Hasil tidak valid: <strong>{{ $staleLabels }}</strong>. Finalize CKPN akan <strong>diblokir</strong> sampai modul tersebut dihitung ulang.</p>
        </div>
    </div>
@endif

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">Detail CKPN Individual & Kolektif</h2>
            <p class="text-sm text-gray-600 mt-1">Hitung CKPN final pasca LGD & PD tersedia.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ $finalizeRoute }}" data-progress data-confirm-title="Konfirmasi Finalisasi CKPN" data-confirm-message="CKPN final periode ini akan dihitung ulang (Individual + Kolektif)." data-confirm-type="warning" data-confirm-button="Ya, Hitung Ulang" data-loading-message="Menghitung CKPN final…">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700">Hitung Ulang CKPN</button>
            </form>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Ekspor Ringkas</a>
            <a href="{{ route('calculation.export.full.period', $period->id) }}" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 shadow-sm" title="Download seluruh hasil perhitungan dalam 1 file Excel multi-sheet (9 Sheet) untuk audit & backtesting manual">Ekspor Lengkap (9 Sheet)</a>
        </div>
    </div>

    <!-- KPI Metric Cards (Visual Hierarchy: Total CKPN as Prime Hero Card) -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-6">
        <div class="md:col-span-2 rounded-xl border-2 border-amber-300/80 p-5 bg-gradient-to-br from-amber-50/60 to-white shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 uppercase">Hasil Konsolidasi</span>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mt-1">Total CKPN</p>
                </div>
                <div class="w-9 h-9 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <div class="mt-4">
                <p class="text-3xl font-extrabold text-gray-900 tracking-tight font-mono">Rp {{ number_format((float) ($summary['total_ckpn'] ?? 0), 0, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Cadangan kolektif + individual</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Individual</p>
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                </div>
                <p class="text-lg font-bold text-gray-900 mt-2 font-mono">Rp {{ number_format((float) ($summary['individual_ckpn'] ?? 0), 0, ',', '.') }}</p>
                <p class="text-[11px] text-gray-500 mt-1"><span class="font-mono">{{ $summary['count_individual'] ?? 0 }}</span> debitur</p>
            </div>
            <p class="text-[11px] text-gray-400 mt-2 pt-2 border-t border-gray-100 font-mono">EAD: {{ number_format((float) ($summary['individual_ead'] ?? 0), 0, ',', '.') }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Kolektif</p>
                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                </div>
                <p class="text-lg font-bold text-gray-900 mt-2 font-mono">Rp {{ number_format((float) ($summary['collective_ckpn'] ?? 0), 0, ',', '.') }}</p>
                <p class="text-[11px] text-gray-500 mt-1"><span class="font-mono">{{ $summary['count_collective'] ?? 0 }}</span> group</p>
            </div>
            <p class="text-[11px] text-gray-400 mt-2 pt-2 border-t border-gray-100 font-mono">EAD: {{ number_format((float) ($summary['collective_ead'] ?? 0), 0, ',', '.') }}</p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Total EAD Portofolio</p>
            <p class="text-lg font-bold text-gray-900 mt-2 font-mono">Rp {{ number_format((float) (($summary['individual_ead'] ?? 0) + ($summary['collective_ead'] ?? 0)), 0, ',', '.') }}</p>
            <p class="text-[11px] text-gray-500 mt-1">Eksposur total</p>
        </div>
    </div>
</div>

<div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">CKPN Individual</h3>
        </div>
        <div class="overflow-x-auto max-h-[500px]">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-600 uppercase sticky top-0">
                    <tr><th class="px-3 py-2 text-left">Loan</th><th class="px-3 py-2 text-right">Nilai Tercatat</th><th class="px-3 py-2 text-right">Nilai Agunan</th><th class="px-3 py-2 text-right">Biaya Penjualan</th><th class="px-3 py-2 text-right">Recovery</th><th class="px-3 py-2 text-right">CKPN</th><th class="px-3 py-2 text-left">Metode</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($individuals as $r)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $r->loan_number }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->nilai_tercatat, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) ($r->liquidity_value ?? 0), 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right text-red-600 font-mono">-{{ number_format((float) ($r->sale_cost ?? 0), 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->estimated_recovery, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-semibold font-mono">{{ number_format((float) $r->ckpn_individual, 0, ',', '.') }}</td>
                            <td class="px-3 py-2">{{ $r->recovery_method }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada CKPN individual.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100">
            <h3 class="text-sm font-bold text-gray-900">CKPN Kolektif</h3>
        </div>
        <div class="overflow-x-auto max-h-[500px]">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-600 uppercase sticky top-0">
                    <tr><th class="px-3 py-2 text-left">Group</th><th class="px-3 py-2 text-right">Bucket / Rating</th><th class="px-3 py-2 text-right">EAD</th><th class="px-3 py-2 text-right">PD</th><th class="px-3 py-2 text-right">LGD</th><th class="px-3 py-2 text-right">CKPN</th><th class="px-3 py-2 text-right">Members</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($collectives as $r)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ (int) $r->bucket_no }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->total_ead, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->pd * 100, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $r->lgd * 100, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right font-semibold font-mono">{{ number_format((float) $r->ckpn_kolektif, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ (int) $r->member_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-3 py-6 text-center text-gray-500">Belum ada CKPN kolektif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection