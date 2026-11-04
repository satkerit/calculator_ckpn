@extends('layouts.app')

@section('title', 'LGD Collateral Shortfall')
@section('page-title', '6. LGD Collateral Shortfall')

@section('content')
@php
    $runRoute    = route('calculation.module.lgd_cs.run');
    $exportRoute = route('calculation.export.module.period', ['module' => 'lgd-cs', 'assessmentPeriod' => $period->id]);
    $detailRoute = route('calculation.module.lgd_detail', ['method' => 'CS', 'assessment_period_id' => $period->id]);
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">LGD Collateral Shortfall (Shortfall Agunan)</h2>
            <p class="text-sm text-gray-600 mt-1">Populasi: <strong>WO 5 tahun beragunan bernilai</strong> + <strong>Kol 5 aktif</strong> · Sale cost: {{ number_format((float) ($config['sale_cost_percentage'] ?? 0) * 100, 2) }}% · Method: <span class="font-semibold">CS</span></p>
            <div class="text-xs text-gray-500 mt-1 space-y-0.5">
                <p>• WO beragunan: Kerugian = OS saat WO − (Net Likuidasi + Recovery Tunai/angsuran sejak WO) · LGD = Kerugian / OS saat WO</p>
                <p>• Kol 5 aktif: LGD = Total Shortfall / Baki Debet 1 bulan terakhir sebelum periode assessment</p>
                <p>WO <em>tanpa</em> agunan (likuid ≤ 0) masuk populasi <strong>LGD ER</strong>.</p>
            </div>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ $runRoute }}" data-progress data-confirm-title="Konfirmasi Hitung LGD CS" data-confirm-message="Proses akan menghitung ulang LGD Collateral Shortfall berbasis data agunan." data-confirm-type="warning" data-confirm-button="Ya, Hitung" data-loading-message="Menghitung LGD Collateral Shortfall…">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">Hitung LGD CS</button>
            </form>
            <a href="{{ $detailRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Detail Debitur</a>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">Ekspor XLSX</a>
        </div>
    </div>

    @if (! empty($lgdStale))
        {{-- Hasil tidak valid: dimensi segmentasi berubah setelah run terakhir --}}
        <div class="mt-6 p-4 rounded-xl border border-red-200 bg-red-50 flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0"><svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg></div>
            <div>
                <p class="text-sm font-semibold text-red-900">Hasil LGD TIDAK VALID - segmentasi berubah</p>
                <p class="text-xs text-red-700 mt-1">Dimensi segmentasi berubah setelah perhitungan terakhir sehingga group_key LGD tidak lagi cocok. Klik <strong>Hitung LGD CS</strong> untuk memperbarui sebelum Finalize CKPN.</p>
            </div>
        </div>
    @endif

    <!-- KPI Metric Cards (Visual Hierarchy: Average LGD as Primary Stat) -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="md:col-span-2 rounded-xl border-2 border-blue-300/80 p-5 bg-gradient-to-br from-blue-50/60 to-white shadow-sm flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-100 text-blue-800 uppercase">Metrik Utama</span>
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide mt-1">Average LGD (Collateral Shortfall)</p>
                </div>
                <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-700 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                </div>
            </div>
            <div class="mt-3">
                <p class="text-3xl font-extrabold text-gray-900 tracking-tight font-mono">{{ number_format((float) ($rows['avg_lgd'] ?? 0), 4, ',', '.') }}</p>
                <p class="text-xs text-gray-500 mt-1">Rata-rata LGD shortfall agunan (WO beragunan &amp; Kol 5 aktif)</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Jumlah Groups</p>
            <p class="text-2xl font-bold text-gray-900 mt-2 font-mono">{{ $rows['count'] ?? 0 }}</p>
            <p class="text-xs text-gray-500 mt-1">Grup segmentasi terhitung</p>
        </div>

        <div class="rounded-xl border border-gray-200 p-4 bg-white flex flex-col justify-between">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Metode</p>
            <p class="text-2xl font-bold text-gray-900 mt-2 font-mono">CS</p>
            <p class="text-xs text-gray-500 mt-1">Collateral Shortfall</p>
        </div>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900">Hasil LGD CS per Group</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Group Key</th>
                    <th class="px-3 py-2 text-right">Exposure WO</th>
                    <th class="px-3 py-2 text-right">Shortfall</th>
                    <th class="px-3 py-2 text-right">Coverage</th>
                    <th class="px-3 py-2 text-right">LGD</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows['rows'] ?? [] as $r)
                    <tr class="hover:bg-gray-50/70">
                        <td class="px-3 py-2 font-mono text-gray-900">{{ $r->group_key }}</td>
                        <td class="px-3 py-2 text-right font-mono text-gray-900">Rp {{ number_format((float) ($r->total_exposure_wo ?? 0), 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-gray-900">Rp {{ number_format((float) ($r->total_shortfall ?? 0), 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono text-gray-900">{{ number_format((float) ($r->collateral_coverage ?? 0), 4, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-mono font-bold text-gray-900">{{ number_format((float) $r->lgd_calculated * 100, 2, ',', '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada hasil LGD CS.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection