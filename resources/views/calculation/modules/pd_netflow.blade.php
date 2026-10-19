@extends('layouts.app')

@section('title', 'PD Net Flow')
@section('page-title', '3. PD Net Flow')

@section('content')
@php
    $runRoute    = route('calculation.module.pd_netflow.run');
    $exportRoute = route('calculation.export.module.period', ['module' => 'pd-netflow', 'assessmentPeriod' => $period->id]);
    $backtestRoute = route('calculation.export.pd_netflow_backtest.period', ['assessmentPeriod' => $period->id]);
    $detailUrl   = route('calculation.module.pd_netflow.detail', ['assessmentPeriod' => $period->id]);
    $hasHistory  = ($results['count'] ?? 0) > 0;
    // Auto tampilkan data bila datang dari link ?show=1 atau redirect setelah run().
    $autoShow    = request()->boolean('show') || session()->has('success') || session()->has('result');
@endphp

<div x-data="{ showData: {{ ($hasHistory && $autoShow) ? 'true' : 'false' }} }">
    <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-gray-900">PD Net Flow per Grup Segmentasi</h2>
                <p class="text-sm text-gray-600 mt-1">Window historis: {{ $config['historical_months'] ?? 24 }} bulan · Method: <span class="font-semibold">NET_FLOW</span></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if (!$hasHistory)
                    {{-- Belum ada riwayat: tombol Hitung PD + matriks disabled --}}
                    <form method="POST" action="{{ $runRoute }}" data-progress>
                        @csrf
                        <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent text-white text-sm font-semibold rounded-lg hover:bg-blue-700 shadow-sm transition-colors duration-200">▶ Hitung PD</button>
                    </form>
                    <button type="button" disabled title="Lakukan perhitungan PD Net Flow terlebih dahulu" class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 text-sm font-semibold text-gray-400 rounded-lg cursor-not-allowed">
                        <svg class="w-4 h-4 mr-1.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Semua Matriks Segmentasi
                    </button>
                @else
                    {{-- Ada riwayat: Tampilkan PD dulu, lalu Rekalkulasi + matriks aktif --}}
                    <template x-if="!showData">
                        <button type="button" @click="showData = true" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent text-white text-sm font-semibold rounded-lg hover:bg-blue-700 shadow-sm transition-colors duration-200">
                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Tampilkan PD
                        </button>
                    </template>
                    <template x-if="showData">
                        <form method="POST" action="{{ $runRoute }}" data-progress data-confirm-title="Konfirmasi Rekalkulasi PD Net Flow" data-confirm-message="Hasil perhitungan sebelumnya akan diganti dengan hasil baru." data-confirm-type="warning" data-confirm-button="Ya, Rekalkulasi" data-loading-message="Menghitung PD Net Flow, mohon tunggu…">
                            @csrf
                            <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-amber-600 border border-transparent text-white text-sm font-semibold rounded-lg hover:bg-amber-700 shadow-sm transition-colors duration-200">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Rekalkulasi
                            </button>
                        </form>
                    </template>
                    {{-- Matriks aktif hanya setelah data ditampilkan --}}
                    <a href="{{ $detailUrl }}" x-show="showData" x-cloak class="inline-flex items-center px-4 py-2 bg-indigo-50 border border-indigo-200 text-sm font-semibold text-indigo-700 rounded-lg hover:bg-indigo-100 hover:border-indigo-300 shadow-sm transition-colors duration-200">
                        <svg class="w-4 h-4 mr-1.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Semua Matriks Segmentasi
                    </a>
                @endif
                <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-sm font-semibold text-gray-700 rounded-lg hover:bg-gray-50 shadow-sm transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1.5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Export XLSX
                </a>
                @if ($hasHistory)
                    <a href="{{ $backtestRoute }}" data-confirm-title="Export PD Net Flow Detail" data-confirm-message="Export akan memproses seluruh data matriks detail PD Net Flow (Tabel 1 Min. Bucket, Tabel 2 Perpindahan Bucket, Tabel 3 Compound Flow to Loss) ke file XLSX. Lanjutkan?" data-confirm-type="info" data-confirm-button="Ya, Export" class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 shadow-sm transition-colors duration-200">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Export Detail
                    </a>
                @endif
            </div>
        </div>

        @if (! empty($pdStale) && $hasHistory)
            {{-- Hasil tidak valid: konfigurasi (segmentasi/window/metode PD) berubah setelah run terakhir --}}
            <div class="mt-6 p-4 rounded-xl border border-red-200 bg-red-50 flex items-start gap-3">
                <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0">⚠</div>
                <div>
                    <p class="text-sm font-semibold text-red-900">Hasil PD Net Flow TIDAK VALID — konfigurasi berubah</p>
                    <p class="text-xs text-red-700 mt-1">Segmentasi (kombinasi dimensi), historical window, atau metode PD berubah setelah perhitungan terakhir, sehingga hasil di bawah ini tidak lagi akurat. Klik <strong>Rekalkulasi</strong> untuk memperbarui sebelum melanjutkan ke modul LGD / CKPN.</p>
                </div>
            </div>
        @endif

        @if ($hasHistory)
            {{-- Info riwayat tersedia, muncul sebelum data ditampilkan --}}
            <div x-show="!showData" x-cloak class="mt-6 p-4 rounded-xl border border-blue-100 bg-blue-50/60 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center font-bold">ℹ</div>
                    <div>
                        <p class="text-sm font-semibold text-blue-900">Riwayat perhitungan PD Net Flow ditemukan</p>
                        <p class="text-xs text-blue-700">Terdapat {{ $results['count'] }} grup segmentasi yang telah dihitung pada periode ini. Klik tombol <strong>Tampilkan PD</strong> untuk melihat hasil.</p>
                    </div>
                </div>
                <button type="button" @click="showData = true" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg shadow-sm shrink-0">
                    Tampilkan PD
                </button>
            </div>
        @else
            <div class="mt-6 p-4 rounded-xl border border-amber-100 bg-amber-50/60 flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-bold">!</div>
                <div>
                    <p class="text-sm font-semibold text-amber-900">Belum ada riwayat perhitungan</p>
                    <p class="text-xs text-amber-700">Silakan klik tombol <strong>Hitung PD</strong> untuk memproses kalkulasi PD Net Flow periode ini.</p>
                </div>
            </div>
        @endif

        {{-- Summary cards + tabel: hanya muncul setelah data ditampilkan --}}
        <div x-show="showData" x-cloak x-transition class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
            <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
                <p class="text-xs font-semibold text-gray-600 uppercase">Groups</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ $results['count'] ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
                <p class="text-xs font-semibold text-gray-600 uppercase">Average PD</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) ($results['avg_pd'] ?? 0) * 100, 2, ',', '.') }}%</p>
            </div>
            <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
                <p class="text-xs font-semibold text-gray-600 uppercase">Method</p>
                <p class="text-2xl font-bold text-gray-900 mt-1">NET_FLOW</p>
            </div>
        </div>
    </div>

    <div x-show="showData" x-cloak x-transition class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-900">Hasil PD per Segmentasi &times; Bucket</h3>
            <a href="{{ $detailUrl }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                Lihat Semua Matriks Segmentasi &rarr;
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-600 uppercase">
                    <tr>
                        <th class="px-3 py-2 text-left">Group Key</th>
                        <th class="px-3 py-2 text-right">Bucket</th>
                        <th class="px-3 py-2 text-right">PD</th>
                        <th class="px-3 py-2 text-right">Periods</th>
                        <th class="px-3 py-2 text-left">Detail</th>
                        <th class="px-3 py-2 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($results['rows'] ?? [] as $r)
                        @php
                            $detail = json_decode((string) $r->detail, true) ?: [];
                            $bucketNo = (int) ($r->bucket_no ?? ($detail['bucket_no'] ?? 1));
                            $bucketLabel = $detail['bucket_label'] ?? null;
                        @endphp
                        <tr>
                            <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                            <td class="px-3 py-2 text-right">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 font-semibold">{{ $bucketNo }}</span>
                                @if ($bucketLabel)
                                    <span class="block text-[10px] text-gray-500 mt-0.5">{{ $bucketLabel }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-semibold text-blue-700">{{ number_format((float) $r->pd_calculated * 100, 2, ',', '.') }}%</td>
                            <td class="px-3 py-2 text-right">{{ (int) $r->periods_analyzed }} bln</td>
                            <td class="px-3 py-2 text-gray-600 font-mono">{{ Str::limit((string) $r->detail, 80) }}</td>
                            <td class="px-3 py-2 text-center">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('calculation.module.pd_netflow.detail', ['assessmentPeriod' => $period->id, 'group_key' => $r->group_key]) }}" class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 hover:bg-blue-100 rounded text-xs font-semibold">
                                        Lihat Matriks
                                    </a>
                                    <a href="{{ $backtestRoute }}?group_key={{ urlencode($r->group_key) }}" class="inline-flex items-center px-2 py-1 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 rounded text-xs font-semibold" title="Export backtesting grup ini (Bucket Snapshot, Transisi, Compound Flow)">
                                        Export
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-gray-500">Belum ada hasil PD.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
