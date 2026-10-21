@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">LGD Weighted</h1>
            <p class="text-sm text-gray-600 mt-1">Kombinasi LGD ER + LGD CS per Segmentasi</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('calculation.module.lgd_er') }}" class="px-4 py-2 bg-white border border-gray-300 rounded hover:bg-gray-50">LGD ER</a>
            <a href="{{ route('calculation.module.lgd_cs') }}" class="px-4 py-2 bg-white border border-gray-300 rounded hover:bg-gray-50">LGD CS</a>
            <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">← Kembali</a>
        </div>
    </div>

    @if ($period)
    <div class="bg-white rounded-lg shadow p-5 mb-5">
        <div class="flex items-center justify-between">
            <div>
                <span class="text-sm text-gray-600">Periode:</span>
                <span class="font-bold text-gray-800 ml-2">{{ $period->assessment_date->translatedFormat('F Y') }}</span>
            </div>
            <div>
                <label class="text-sm text-gray-600 mr-2">Pilih Periode:</label>
                <select onchange="window.location.href='?assessment_period_id='+this.value" class="border border-gray-300 rounded px-3 py-1 text-sm">
                    @foreach ($periods as $p)
                        <option value="{{ $p->id }}" @if($p->id == $period->id) selected @endif>{{ $p->assessment_date->translatedFormat('F Y') }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
    @endif

    @if ($emptyNotice)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 flex items-start gap-3">
            <div class="w-8 h-8 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center font-bold shrink-0">⚠</div>
            <p class="text-sm text-amber-900">{{ $emptyNotice }}</p>
        </div>
    @endif

    @php
        $fmt = fn($v) => number_format((float) $v, 2, ',', '.');
        $pct = fn($v) => number_format((float) $v * 100, 2, ',', '.') . '%';
    @endphp

    {{-- Tabel Segmentasi per Group Key --}}
    @if ($groupSummary && $groupSummary->count() > 0)
    <div class="mt-5">
        <h3 class="text-sm font-bold text-gray-700 mb-2">LGD Weighted per Segmentasi</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs border border-gray-300">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 border border-gray-300 text-left">Group Key</th>
                        <th class="px-3 py-2 border border-gray-300 text-right">WO ER</th>
                        <th class="px-3 py-2 border border-gray-300 text-right">Recovery ER</th>
                        <th class="px-3 py-2 border border-gray-300 text-right">Loss ER</th>
                        <th class="px-3 py-2 border border-gray-300 text-right">Baki CS</th>
                        <th class="px-3 py-2 border border-gray-300 text-right">Shortfall CS</th>
                        <th class="px-3 py-2 border border-gray-300 text-right font-bold">Total WO</th>
                        <th class="px-3 py-2 border border-gray-300 text-right font-bold">Total LGD</th>
                        <th class="px-3 py-2 border border-gray-300 text-right font-bold">LGD Weighted</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groupSummary as $g)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 border border-gray-300 font-mono">{{ $g->group_key }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($g->wo_er) }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($g->recovery_er) }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($g->loss_er) }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($g->baki_cs) }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($g->shortfall_cs) }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right font-bold">{{ $fmt($g->total_wo) }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right font-bold">{{ $fmt($g->total_lgd) }}</td>
                        <td class="px-3 py-2 border border-gray-300 text-right font-bold text-indigo-700">{{ $pct($g->lgd_weighted_rate) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Total Ringkasan --}}
    @if ($totalSummary)
    <div class="mt-5">
        <h3 class="text-sm font-bold text-gray-700 mb-2">Total Keseluruhan</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs border border-gray-300">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 border border-gray-300 text-left" style="width:220px"></th>
                        <th class="px-3 py-2 border border-gray-300 text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Total WO ER</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($totalSummary->wo_er) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Total Recovery ER</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($totalSummary->recovery_er) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Total Loss ER</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($totalSummary->loss_er) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Total Baki Debet CS</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($totalSummary->baki_cs) }}</td>
                    </tr>
                    <tr>
                        <td class="px-3 py-2 border border-gray-300 font-semibold bg-gray-50">Total Shortfall CS</td>
                        <td class="px-3 py-2 border border-gray-300 text-right">{{ $fmt($totalSummary->shortfall_cs) }}</td>
                    </tr>
                    <tr class="bg-blue-50">
                        <td class="px-3 py-2 border border-gray-300 font-bold">Total WO (ER + CS)</td>
                        <td class="px-3 py-2 border border-gray-300 text-right font-bold">{{ $fmt($totalSummary->total_wo) }}</td>
                    </tr>
                    <tr class="bg-blue-50">
                        <td class="px-3 py-2 border border-gray-300 font-bold">Total LGD (Loss ER + Shortfall CS)</td>
                        <td class="px-3 py-2 border border-gray-300 text-right font-bold">{{ $fmt($totalSummary->total_lgd) }}</td>
                    </tr>
                    <tr class="bg-indigo-100">
                        <td class="px-3 py-2 border border-gray-300 font-bold">LGD Weighted Rate</td>
                        <td class="px-3 py-2 border border-gray-300 text-right font-bold text-indigo-700 text-lg">{{ $pct($totalSummary->lgd_weighted_rate) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
