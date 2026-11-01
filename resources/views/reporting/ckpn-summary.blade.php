@extends('layouts.app')

@section('title', 'Laporan Ringkasan CKPN - CKPN Calculator')

@section('page-title', 'Laporan Ringkasan CKPN')

@section('content')
<div class="space-y-6">
    <!-- Summary Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h3 class="font-bold text-gray-800">Ringkasan CKPN Assessment</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Periode Assessment</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Date</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Total EAD</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Individual CKPN</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Collective CKPN</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Total CKPN</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Coverage %</th>
                        <th class="px-6 py-3 text-center font-semibold text-gray-700">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($reports as $report)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $report['assessment']->period_name }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $report['assessment']->assessment_date->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-right font-semibold">{{ number_format($report['ead'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right text-emerald-600 font-semibold">{{ number_format($report['individual_ckpn'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right text-gray-900 font-mono font-semibold">{{ number_format($report['collective_ckpn'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right text-blue-600 font-bold text-lg">{{ number_format($report['total_ckpn'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
                                    {{ number_format($report['coverage_ratio'], 2) }}%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="{{ route('reporting.risk-matrix', ['assessment_id' => $report['assessment']->id]) }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">Lihat</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">Belum ada periode assessment yang selesai</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $assessments->links() }}
    </div>
</div>
@endsection
