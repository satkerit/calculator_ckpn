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
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">▶ Hitung LGD CS</button>
            </form>
            <a href="{{ $detailRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">🔍 Detail Debitur</a>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
        </div>
    </div>

    @if (! empty($lgdStale))
        {{-- Hasil tidak valid: dimensi segmentasi berubah setelah run terakhir --}}
        <div class="mt-6 p-4 rounded-xl border border-red-200 bg-red-50 flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0">⚠</div>
            <div>
                <p class="text-sm font-semibold text-red-900">Hasil LGD TIDAK VALID — segmentasi berubah</p>
                <p class="text-xs text-red-700 mt-1">Dimensi segmentasi berubah setelah perhitungan terakhir sehingga group_key LGD tidak lagi cocok. Klik <strong>Hitung LGD CS</strong> untuk memperbarui sebelum Finalize CKPN.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Groups</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $rows['count'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Average LGD</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) ($rows['avg_lgd'] ?? 0), 4, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Method</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">CS</p>
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
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($r->total_exposure_wo ?? 0), 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($r->total_shortfall ?? 0), 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($r->collateral_coverage ?? 0), 6, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->lgd_calculated * 100, 2, ',', '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-3 py-6 text-center text-gray-500">Belum ada hasil LGD CS.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection