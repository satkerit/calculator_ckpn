@extends('layouts.app')

@section('title', 'Konfigurasi CKPN')
@section('page-title', '1. Konfigurasi CKPN')

@section('content')
@php
    $segmentDims = isset($config['segment_dimensions']) ? explode('|', str_replace(',', '|', $config['segment_dimensions'])) : ['OFFICE'];
    $moduleRoute  = route('calculation.module.config.save');
    $exportRoute  = route('calculation.export.module.period', ['module' => 'config', 'assessmentPeriod' => $period->id]);
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <form method="POST" action="{{ $moduleRoute }}" class="space-y-6" data-confirm-title="Simpan Konfigurasi CKPN" data-confirm-message="Pengaturan metode & parameter perhitungan periode ini akan diperbarui." data-confirm-button="Ya, Simpan" data-loading-message="Menyimpan konfigurasi…">
        @csrf
        <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">PD Method</label>
                <select name="pd_method" class="w-full border-gray-300 rounded-lg text-sm">
                    <option value="NET_FLOW" {{ ($config['pd_method'] ?? '') === 'NET_FLOW' ? 'selected' : '' }}>Net Flow (compound)</option>
                    <option value="MIGRATION" {{ ($config['pd_method'] ?? '') === 'MIGRATION' ? 'selected' : '' }}>Migration (rating)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">LGD Method</label>
                <select name="lgd_method" class="w-full border-gray-300 rounded-lg text-sm">
                    <option value="WEIGHTED" {{ ($config['lgd_method'] ?? 'WEIGHTED') === 'WEIGHTED' ? 'selected' : '' }}>Weighted (ER + CS)</option>
                    <option value="EXPECTED_RECOVERY" {{ ($config['lgd_method'] ?? '') === 'EXPECTED_RECOVERY' ? 'selected' : '' }}>Expected Recovery (ER)</option>
                    <option value="COLLATERAL_SHORTFALL" {{ ($config['lgd_method'] ?? '') === 'COLLATERAL_SHORTFALL' ? 'selected' : '' }}>Collateral Shortfall (CS)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Weighted = penjumlahan Loss &amp; Exposure ER + CS sesuai kertas kerja; ER/CS = pakai satu metode saja.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Historical Window (bulan)</label>
                <input type="number" min="12" max="120" name="historical_months" value="{{ $config['historical_months'] ?? 24 }}" class="w-full border-gray-300 rounded-lg text-sm">
                <p class="text-xs text-gray-500 mt-1">Dipakai untuk kalkulasi PD (Net Flow / Migration).</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Historical Window LGD (bulan)</label>
                <input type="number" min="12" max="120" name="lgd_historical_months" value="{{ $config['lgd_historical_months'] ?? 60 }}" class="w-full border-gray-300 rounded-lg text-sm">
                <p class="text-xs text-gray-500 mt-1">Window data snapshot untuk LGD (default 60 bulan / 5 tahun).</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">Sale Cost (%)</label>
                <input type="number" step="0.0001" min="0" max="1" name="sale_cost_percentage" value="{{ $config['sale_cost_percentage'] ?? 0.02 }}" class="w-full border-gray-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">TOP N Outstanding (Individual)</label>
                <input type="number" min="0" max="10000" name="individual_top_n" value="{{ $config['individual_top_n'] ?? 20 }}" class="w-full border-gray-300 rounded-lg text-sm">
                <p class="text-xs text-gray-500 mt-1">Ambil N debitur NPL dengan outstanding terbesar.</p>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">NPL — Kualitas (Kolektibilitas)</label>
                <div class="flex items-center gap-4">
                    @foreach ([3, 4, 5] as $q)
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="npl_ratings[]" value="{{ $q }}"
                                   @checked(in_array($q, $config['npl_ratings'] ?? [3,4,5], true))
                                   class="rounded border-gray-300">
                            Kualitas {{ $q }}
                        </label>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 mt-1">Pembiayaan dengan kualitas terpilih dikategorikan NPL.</p>
            </div>
        </div>

        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Segmentasi (combo, pilih ≥1)</label>
            <div class="flex flex-wrap gap-3">
                @foreach (['OFFICE' => 'Per Lokasi (Office)', 'AKAD' => 'Per Akad', 'USAGE' => 'Per Jenis Penggunaan'] as $k => $label)
                    <label class="inline-flex items-center gap-2 px-3 py-2 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" name="segment_dimensions[]" value="{{ $k }}" {{ in_array($k, $segmentDims, true) ? 'checked' : '' }}>
                        <span class="text-sm">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-1">group_key = OFFICE|AKAD|USAGE sesuai dimensi yang dipilih.</p>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">Simpan Konfigurasi</button>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
        </div>
    </form>
</div>

<div class="mt-6 bg-blue-50 border border-blue-100 rounded-xl p-4 text-sm text-blue-900">
    <p class="font-semibold mb-1">Periode aktif: {{ $period->assessment_date?->format('Y-m-d') ?? '-' }} (ID: {{ $period->id }})</p>
    <p>Konfigurasi ini akan dipakai oleh Modul 2–8. Segmentasi menentukan group_key kolektif.</p>
    <p class="mt-2"><strong>Perhatian:</strong> perubahan segmentasi membatalkan hasil PD, LGD &amp; CKPN Kolektif; perubahan historical window / metode PD membatalkan hasil PD &amp; CKPN Kolektif. Hasil yang dibatalkan otomatis ditandai <em>tidak valid</em> dan wajib dihitung ulang sebelum Finalize CKPN.</p>
</div>
@endsection