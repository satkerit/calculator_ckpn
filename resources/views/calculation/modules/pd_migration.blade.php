@extends('layouts.app')

@section('title', 'PD Migration')
@section('page-title', '4. PD Migration')

@section('content')
@php
    $runRoute    = route('calculation.module.pd_migration.run');
    $exportRoute = route('calculation.export.module.period', ['module' => 'pd-migration', 'assessmentPeriod' => $period->id]);
    // Label kolom matriks sesuai kertas kerja B2.PD-Migration: rating 1..5 + Hapus Buku + Pembayaran.
    $destLabels = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => 'Hapus Buku', 7 => 'Pembayaran'];
@endphp

<div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-base font-bold text-gray-900">PD Migration (Quality Rating) per Grup Segmentasi</h2>
            <p class="text-sm text-gray-600 mt-1">Rentang historis mengikuti snapshot aktual loan grup (bukan konfigurasi window). Method: <span class="font-semibold">MIGRATION</span></p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ $runRoute }}" data-progress data-confirm-title="Konfirmasi Hitung PD Migration" data-confirm-message="Proses akan menghitung ulang PD berbasis quality rating migration untuk seluruh grup." data-confirm-type="warning" data-confirm-button="Ya, Hitung" data-loading-message="Menghitung PD Migration…">
                @csrf
                <input type="hidden" name="assessment_period_id" value="{{ $period->id }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700">▶ Hitung PD Migration</button>
            </form>
            <a href="{{ $exportRoute }}" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 hover:bg-gray-50">⬇ Export XLSX</a>
        </div>
    </div>

    @if (! empty($pdStale))
        {{-- Hasil tidak valid: konfigurasi (segmentasi/window/metode PD) berubah setelah run terakhir --}}
        <div class="mt-6 p-4 rounded-xl border border-red-200 bg-red-50 flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-red-100 text-red-600 flex items-center justify-center font-bold shrink-0">⚠</div>
            <div>
                <p class="text-sm font-semibold text-red-900">Hasil PD Migration TIDAK VALID — konfigurasi berubah</p>
                <p class="text-xs text-red-700 mt-1">Segmentasi (kombinasi dimensi) atau metode PD berubah setelah perhitungan terakhir. Klik <strong>Hitung PD Migration</strong> untuk memperbarui sebelum melanjutkan ke modul LGD / CKPN.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Groups</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $results['count'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Average PD</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) ($results['avg_pd'] ?? 0), 4, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Method</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">MIGRATION</p>
        </div>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900">Hasil PD per Group</h3>
        <p class="text-xs text-gray-500 mt-1">Bobot saldo (outstanding). Klik baris untuk melihat matriks 5×7 &amp; PD per rating.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full text-xs">
            <thead class="bg-gray-50 text-gray-600 uppercase">
                <tr>
                    <th class="px-3 py-2 text-left">Group Key</th>
                    <th class="px-3 py-2 text-right">PD Grup</th>
                    <th class="px-3 py-2 text-right">Transisi</th>
                    <th class="px-3 py-2 text-right">PD R1</th>
                    <th class="px-3 py-2 text-right">PD R2</th>
                    <th class="px-3 py-2 text-right">PD R3</th>
                    <th class="px-3 py-2 text-right">PD R4</th>
                    <th class="px-3 py-2 text-right">PD R5</th>
                    <th class="px-3 py-2 text-center">Detail</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($results['rows'] ?? [] as $r)
                    @php
                        $hasMatrix = ! empty($r->matrix);
                        $rowId = 'pmig-row-'.md5($r->group_key.$period->id);
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-mono">{{ $r->group_key }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format((float) $r->pd_calculated * 100, 2, ',', '.') }}%</td>
                        <td class="px-3 py-2 text-right">{{ (int) $r->periods_analyzed }}</td>
                        @foreach ([1,2,3,4,5] as $rr)
                            <td class="px-3 py-2 text-right text-gray-700">{{ isset($r->rating_pd[$rr]) ? number_format((float) $r->rating_pd[$rr] * 100, 2, ',', '.') . '%' : '—' }}</td>
                        @endforeach
                        <td class="px-3 py-2 text-center">
                            @if ($hasMatrix)
                                <button type="button" onclick="document.getElementById('{{ $rowId }}').classList.toggle('hidden')" class="px-2 py-1 text-xs font-semibold rounded border border-gray-300 text-gray-700 hover:bg-gray-100">Lihat Matriks</button>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                    </tr>
                    @if ($hasMatrix)
                        <tr id="{{ $rowId }}" class="hidden bg-gray-50">
                            <td colspan="9" class="px-4 py-4">
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <div class="lg:col-span-2">
                                        <p class="text-xs font-bold text-gray-700 uppercase mb-2">Matriks Migrasi (rate rata-rata per sel, horizon 12 bln)</p>
                                        <table class="min-w-full text-xs border border-gray-200">
                                            <thead class="bg-gray-100 text-gray-700">
                                                <tr>
                                                    <th class="px-2 py-1 text-left">Rating Awal ↓</th>
                                                    @foreach ($destLabels as $dl)
                                                        <th class="px-2 py-1 text-right">{{ $dl }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @for ($src = 1; $src <= 5; $src++)
                                                    <tr class="border-t border-gray-100">
                                                        <td class="px-2 py-1 font-semibold text-gray-700">Rating {{ $src }}</td>
                                                        @for ($tgt = 1; $tgt <= 7; $tgt++)
                                                            @php $v = (float) ($r->matrix[$src][$tgt] ?? 0); @endphp
                                                            <td class="px-2 py-1 text-right font-mono {{ $v > 0 ? 'text-gray-900' : 'text-gray-300' }}">{{ number_format($v * 100, 2, ',', '.') }}%</td>
                                                        @endfor
                                                    </tr>
                                                @endfor
                                            </tbody>
                                        </table>
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold text-gray-700 uppercase mb-2">Exposure posisi assessment (rupiah)</p>
                                        <table class="min-w-full text-xs border border-gray-200">
                                            <tbody>
                                                @for ($rr = 1; $rr <= 5; $rr++)
                                                    <tr class="border-t border-gray-100">
                                                        <td class="px-2 py-1 font-semibold text-gray-700">Rating {{ $rr }}</td>
                                                        <td class="px-2 py-1 text-right font-mono">{{ number_format((float) ($r->rating_exposure[$rr] ?? 0), 0, ',', '.') }}</td>
                                                    </tr>
                                                @endfor
                                                <tr class="border-t border-gray-300 bg-gray-100">
                                                    <td class="px-2 py-1 font-bold text-gray-900">Total</td>
                                                    @php $totalExp = array_sum($r->rating_exposure ?? []); @endphp
                                                    <td class="px-2 py-1 text-right font-mono font-bold">{{ number_format((float) $totalExp, 0, ',', '.') }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="9" class="px-3 py-6 text-center text-gray-500">Belum ada hasil PD.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection