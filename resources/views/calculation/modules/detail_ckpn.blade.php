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
        <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0">⚠</div>
        <div>
            <p class="text-sm font-semibold text-red-900">Sebagian hasil KURANG AKURAT / TIDAK VALID — konfigurasi berubah</p>
            <p class="text-xs text-red-700 mt-1">Konfigurasi (segmentasi / historical window / metode PD) berubah setelah perhitungan terakhir. Hasil tidak valid: <strong>{{ $staleLabels }}</strong>. Finalize CKPN akan <strong>diblokir</strong> sampai modul tersebut dihitung ulang.</p>
        </div>
    </div>
@endif

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">Detail CKPN Individual & Kolektif</h2>
            <p class="text-sm text-gray-600 mt-1">Hitung CKPN final pasca LGD & PD tersedia.</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ $finalizeRoute }}" data-progress data-confirm-title="Konfirmasi Finalisasi CKPN" data-confirm-message="CKPN final periode ini akan dihitung ulang (Individual + Kolektif)." data-confirm-type="warning" data-confirm-button="Ya, Hitung Ulang" data-loading-message="Menghitung CKPN final…">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700">▶ Hitung Ulang CKPN</button>
            </form>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Total CKPN</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format((float) ($summary['total_ckpn'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200 p-4 bg-emerald-50">
            <p class="text-xs font-semibold text-emerald-700 uppercase">Individual</p>
            <p class="text-xl font-bold text-emerald-900 mt-1">{{ number_format((float) ($summary['individual_ckpn'] ?? 0), 0, ',', '.') }}</p>
            <p class="text-[11px] text-emerald-700 mt-1">{{ $summary['count_individual'] ?? 0 }} debitur</p>
        </div>
        <div class="rounded-xl border border-amber-200 p-4 bg-amber-50">
            <p class="text-xs font-semibold text-amber-700 uppercase">Kolektif</p>
            <p class="text-xl font-bold text-amber-900 mt-1">{{ number_format((float) ($summary['collective_ckpn'] ?? 0), 0, ',', '.') }}</p>
            <p class="text-[11px] text-amber-700 mt-1">{{ $summary['count_collective'] ?? 0 }} group</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">EAD Individual</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format((float) ($summary['individual_ead'] ?? 0), 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">EAD Kolektif</p>
            <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format((float) ($summary['collective_ead'] ?? 0), 0, ',', '.') }}</p>
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
                    <tr><th class="px-3 py-2 text-left">Loan</th><th class="px-3 py-2 text-right">Nilai Tercatat</th><th class="px-3 py-2 text-right">Recovery</th><th class="px-3 py-2 text-right">CKPN</th><th class="px-3 py-2 text-left">Method</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($individuals as $r)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $r->loan_number }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $r->nilai_tercatat, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $r->estimated_recovery, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->ckpn_individual, 0, ',', '.') }}</td>
                            <td class="px-3 py-2">{{ $r->recovery_method }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada CKPN individual.</td></tr>
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
                    <tr><th class="px-3 py-2 text-left">Group</th><th class="px-3 py-2 text-right">EAD</th><th class="px-3 py-2 text-right">PD</th><th class="px-3 py-2 text-right">LGD</th><th class="px-3 py-2 text-right">CKPN</th><th class="px-3 py-2 text-right">Members</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($collectives as $r)
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $r->total_ead, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $r->pd * 100, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right">{{ number_format((float) $r->lgd * 100, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->ckpn_kolektif, 0, ',', '.') }}</td>
                            <td class="px-3 py-2 text-right">{{ (int) $r->member_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada CKPN kolektif.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection