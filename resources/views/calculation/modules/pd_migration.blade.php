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
            <p class="text-sm text-gray-600 mt-1">Window historis: {{ $histM }} bulan → {{ $numMatrices }} matriks triwulan (transisi 12 bulan per matriks) · basis kualitas/Kol 1–5 + Hapus Buku &amp; Pembayaran · Method: <span class="font-semibold">MIGRATION</span></p>
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
                <p class="text-xs text-red-700 mt-1">Segmentasi (kombinasi dimensi), historical window, atau metode PD berubah setelah perhitungan terakhir, sehingga matriks triwulan &amp; PD per rating di bawah ini tidak lagi akurat. Klik <strong>Hitung PD Migration</strong> untuk memperbarui sebelum melanjutkan ke modul LGD / CKPN.</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-6">
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Groups</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $results['count'] ?? 0 }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Average PD</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) ($results['avg_pd'] ?? 0), 4, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Window Historis</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $histM }} <span class="text-sm font-medium text-gray-500">bulan</span></p>
        </div>
        <div class="rounded-xl border border-gray-200 p-4 bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 uppercase">Matriks Triwulan</p>
            <p class="text-2xl font-bold text-gray-900 mt-1">{{ $numMatrices }} <span class="text-sm font-medium text-gray-500">× 12 bln</span></p>
        </div>
    </div>
</div>

<div class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
        <div>
            <h3 class="text-sm font-bold text-gray-900">Hasil PD per Group</h3>
            <p class="text-xs text-gray-500 mt-1">Bobot saldo (outstanding). Klik baris untuk melihat matriks 5×7 &amp; PD per rating.</p>
        </div>
        <div class="flex gap-2 shrink-0">
            <button type="button" id="pmig-toggle-all" onclick="pmigToggleAll()" class="inline-flex items-center px-3 py-2 border border-gray-300 text-xs font-semibold rounded-lg text-gray-700 hover:bg-gray-50">Tampilkan Semua Matriks</button>
            <a href="{{ route('calculation.export.pd_migration_matrix.period', $period->id) }}" class="inline-flex items-center px-3 py-2 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700">⬇ Export Detail Matriks</a>
        </div>
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
                        <tr id="{{ $rowId }}" class="pmig-detail hidden bg-gray-50">
                            <td colspan="9" class="px-4 py-4 space-y-6">
                                {{-- Per triwulan: 1 tabel pergerakan outstanding + 1 tabel rate/PD --}}
                                @php $snaps = $r->matrixSnapshots ?? []; @endphp
                                @forelse ($snaps as $s)
                                    @php
                                        $startLabel = $s['t_start_label'] ?? ($s['t_start_ym'] ?? '');
                                        $endLabel   = $s['t_end_label'] ?? ($s['t_end_ym'] ?? '');
                                        $mTitik  = $startLabel . ' → ' . $endLabel;
                                        $totalFr = $s['total_from'] ?? [];
                                        $totalTo = $s['total_to'] ?? [];
                                    @endphp
                                    <div>
                                        <p class="text-xs font-bold text-gray-800 uppercase mb-2">
                                            {{ $s['quarter'] ?? '' }} · {{ $mTitik }}
                                        </p>

                                        {{-- Tabel 1: posisi outstanding (rupiah) --}}
                                        <div class="overflow-x-auto mb-6">
                                            <table class="min-w-full text-xs border-collapse border border-gray-400">
                                                <thead>
                                                    <tr class="bg-[#4F81BD] text-white">
                                                        <th rowspan="2" class="px-2 py-1 text-center border border-gray-400 w-20">Kualitas</th>
                                                        <th rowspan="2" class="px-2 py-1 text-center border border-gray-400 w-32">Saldo awal<br>{{ $startLabel }}</th>
                                                        <th colspan="7" class="px-2 py-1 text-center border border-gray-400">POSISI {{ $endLabel }}</th>
                                                        <th rowspan="2" class="px-2 py-1 text-center border border-gray-400 w-32">TOTAL</th>
                                                    </tr>
                                                    <tr class="bg-[#4F81BD] text-white">
                                                        @foreach ($destLabels as $dl)
                                                            <th class="px-2 py-1 text-center border border-gray-400">{{ $dl }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @for ($src = 1; $src <= 5; $src++)
                                                        <tr>
                                                            <td class="px-2 py-1 text-center font-bold border border-gray-300 bg-white">{{ $src }}</td>
                                                            <td class="px-2 py-1 text-right font-mono border border-gray-300 bg-white">{{ number_format((float) ($s['saldo_awal'][$src] ?? 0), 0, ',', '.') }}</td>
                                                            @for ($tgt = 1; $tgt <= 7; $tgt++)
                                                                @php $v = (float) ($s['matrix'][$src][$tgt] ?? 0); @endphp
                                                                <td class="px-2 py-1 text-right font-mono border border-gray-300 {{ $v > 0 ? 'bg-white text-gray-900' : 'bg-white text-gray-400' }}">{{ number_format($v, 0, ',', '.') }}</td>
                                                            @endfor
                                                            <td class="px-2 py-1 text-right font-mono font-semibold border border-gray-300 bg-white">{{ number_format((float) ($s['row_total'][$src] ?? 0), 0, ',', '.') }}</td>
                                                        </tr>
                                                    @endfor
                                                    <tr class="bg-[#DCE6F1] font-bold">
                                                        <td class="px-2 py-1 border border-gray-300"></td>
                                                        <td class="px-2 py-1 text-right font-mono border border-gray-300">{{ number_format((float) array_sum($s['saldo_awal'] ?? []), 0, ',', '.') }}</td>
                                                        @for ($tgt = 1; $tgt <= 7; $tgt++)
                                                            <td class="px-2 py-1 border border-gray-300"></td>
                                                        @endfor
                                                        <td class="px-2 py-1 text-right font-mono border border-gray-300">{{ number_format((float) ($s['grand_total'] ?? 0), 0, ',', '.') }}</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        {{-- Tabel 2: rate / PD per sel (proporsi terhadap saldo awal baris) --}}
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full text-xs border-collapse border border-gray-400">
                                                <thead>
                                                    <tr class="bg-[#4F81BD] text-white">
                                                        <th rowspan="2" class="px-2 py-1 text-center border border-gray-400 w-20">Kualitas</th>
                                                        <th rowspan="2" class="px-2 py-1 text-center border border-gray-400 w-32">Saldo awal<br>{{ $startLabel }}</th>
                                                        <th colspan="7" class="px-2 py-1 text-center border border-gray-400">POSISI {{ $endLabel }}</th>
                                                        <th rowspan="2" class="px-2 py-1 text-center border border-gray-400 w-32">TOTAL</th>
                                                    </tr>
                                                    <tr class="bg-[#4F81BD] text-white">
                                                        @foreach ($destLabels as $dl)
                                                            <th class="px-2 py-1 text-center border border-gray-400">{{ $dl }}</th>
                                                        @endforeach
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @for ($src = 1; $src <= 5; $src++)
                                                        @php
                                                            $baseRow = (float) ($s['saldo_awal'][$src] ?? 0);
                                                            $pdRow   = (float) ($s['pd'][$src] ?? 0);
                                                        @endphp
                                                        <tr>
                                                            <td class="px-2 py-1 text-center font-bold border border-gray-300 bg-white">{{ $src }}</td>
                                                            <td class="px-2 py-1 text-right font-mono border border-gray-300 bg-white">{{ number_format($baseRow, 0, ',', '.') }}</td>
                                                            @for ($tgt = 1; $tgt <= 7; $tgt++)
                                                                @php $rv = (float) ($s['rate'][$src][$tgt] ?? 0); @endphp
                                                                <td class="px-2 py-1 text-right font-mono border border-gray-300 {{ $rv > 0 ? 'bg-white text-gray-900' : 'bg-white text-gray-400' }}">{{ number_format($rv * 100, 1, ',', '.') }}%</td>
                                                            @endfor
                                                            <td class="px-2 py-1 text-right font-mono font-bold border border-gray-300 bg-white">{{ number_format($pdRow * 100, 1, ',', '.') }}%</td>
                                                        </tr>
                                                    @endfor
                                                    @php
                                                        $colTotals = $s['total_to'] ?? [];
                                                        $grandRate = array_sum($colTotals) ?: 0.0;
                                                        $grandBase = array_sum($s['saldo_awal'] ?? []);
                                                        $weightedPd = $grandBase > 0
                                                            ? array_sum(array_map(fn ($x) => (float) ($s['pd'][$x] ?? 0) * (float) ($s['saldo_awal'][$x] ?? 0), [1, 2, 3, 4, 5])) / $grandBase
                                                            : 0.0;
                                                    @endphp
                                                    <tr class="bg-[#DCE6F1] font-bold">
                                                        <td class="px-2 py-1 border border-gray-300"></td>
                                                        <td class="px-2 py-1 text-right font-mono border border-gray-300">{{ number_format((float) $grandBase, 0, ',', '.') }}</td>
                                                        @for ($tgt = 1; $tgt <= 7; $tgt++)
                                                            <td class="px-2 py-1 text-right font-mono border border-gray-300">{{ $grandRate > 0 ? number_format(((float) ($colTotals[$tgt] ?? 0) / $grandRate) * 100, 1, ',', '.') . '%' : '0,0%' }}</td>
                                                        @endfor
                                                        <td class="px-2 py-1 text-right font-mono border border-gray-300">{{ number_format($weightedPd * 100, 1, ',', '.') }}%</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                            <p class="text-[11px] text-gray-500 mt-1">Kolom TOTAL per baris = PD per rating, dihitung cascade mundur (R5→R1) mengikuti rumus kertas kerja: <span class="font-mono">H + (G×PD5) + (F×PD4) + (E×PD3) + (D×PD2)</span>.</p>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-xs text-gray-500">Detail matriks tidak tersedia.</p>
                                @endforelse

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                    <div class="lg:col-span-2">
                                        <p class="text-xs font-bold text-gray-700 uppercase mb-2">Matriks Migrasi (rate rata-rata berbobot, horizon 12 bln)</p>
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

<script>
    // Buka/tutup seluruh detail matriks semua grup sekaligus.
    function pmigToggleAll() {
        var rows = document.querySelectorAll('.pmig-detail');
        if (rows.length === 0) return;

        var anyHidden = false;
        rows.forEach(function (el) {
            if (el.classList.contains('hidden')) anyHidden = true;
        });

        rows.forEach(function (el) {
            el.classList.toggle('hidden', !anyHidden);
        });

        var btn = document.getElementById('pmig-toggle-all');
        if (btn) btn.textContent = anyHidden ? 'Sembunyikan Semua Matriks' : 'Tampilkan Semua Matriks';
    }
</script>
@endsection