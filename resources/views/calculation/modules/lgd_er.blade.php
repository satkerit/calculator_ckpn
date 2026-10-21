@extends('layouts.app')

@section('title', 'LGD Expected Recovery')
@section('page-title', '5. LGD Expected Recovery')

@section('content')
@php
    $runRoute    = route('calculation.module.lgd_er.run');
    $exportRoute = route('calculation.export.module.period', ['module' => 'lgd-er', 'assessmentPeriod' => $period->id]);
    $detailRoute = route('calculation.module.lgd_detail', ['method' => 'ER', 'assessment_period_id' => $period->id]);
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">LGD Expected Recovery (Recovery Rate dari Write-Off Tanpa Agunan)</h2>
            <p class="text-sm text-gray-600 mt-1">Populasi: write-off {{ $config['lgd_historical_months'] ?? 60 }} bulan <strong>tanpa agunan bernilai (likuid ≤ 0)</strong> · pengurang: recovery tunai/angsuran sejak WO · Method: <span class="font-semibold">ER</span></p>
            <p class="text-xs text-gray-500 mt-1">Rumus: LGD = (Σ OS saat WO − Σ recovery tunai) / Σ OS saat WO. WO beragunan bernilai dihitung di <strong>LGD CS</strong>.</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ $runRoute }}" data-progress data-confirm-title="Konfirmasi Hitung LGD ER" data-confirm-message="Proses akan menghitung ulang LGD Expected Recovery berbasis data write-off 5 tahun terakhir." data-confirm-type="warning" data-confirm-button="Ya, Hitung" data-loading-message="Menghitung LGD Expected Recovery…">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">▶ Hitung LGD ER</button>
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
                <p class="text-xs text-red-700 mt-1">Dimensi segmentasi berubah setelah perhitungan terakhir sehingga group_key LGD tidak lagi cocok. Klik <strong>Hitung LGD ER</strong> untuk memperbarui sebelum Finalize CKPN.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Groups</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $rows['count'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Average LGD</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) ($rows['avg_lgd'] ?? 0), 4, ',', '.') }}</p>
        </div>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900">Hasil LGD ER per Group</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Group Key</th>
                    <th class="px-3 py-2 text-left">Office</th>
                    <th class="px-3 py-2 text-left">Akad</th>
                    <th class="px-3 py-2 text-left">Usage</th>
                    <th class="px-3 py-2 text-right">Recovery Rate</th>
                    <th class="px-3 py-2 text-right">Total WO</th>
                    <th class="px-3 py-2 text-right">Total Recovery</th>
                    <th class="px-3 py-2 text-right">LGD</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($rows['rows'] ?? [] as $r)
                    <tr>
                        <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                        <td class="px-3 py-2">{{ $r->office_code }}</td>
                        <td class="px-3 py-2">{{ $r->akad_code }}</td>
                        <td class="px-3 py-2">{{ $r->usage_code }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($r->recovery_rate ?? 0) * 100, 2, ',', '.') }}%</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($r->total_write_off ?? 0), 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) ($r->total_recovery ?? 0), 0, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->lgd_calculated * 100, 2, ',', '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-3 py-6 text-center text-gray-500">Belum ada hasil LGD ER.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection