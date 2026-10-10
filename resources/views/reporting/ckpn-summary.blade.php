@extends('layouts.app')

@section('title', 'CKPN Summary Report - CKPN Calculator')

@section('page-title', 'CKPN Summary Report')

@section('content')
<div class="space-y-6">
    <!-- Filters -->
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-4">Report Filters</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Date Range</label>
                <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Status</label>
                <select class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option>All</option>
                    <option>Completed</option>
                    <option>In Progress</option>
                </select>
            </div>
            <div class="flex items-end">
                <button class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">Apply Filters</button>
            </div>
        </div>
    </div>

    <!-- Summary Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b">
            <h3 class="font-bold text-gray-800">Assessment CKPN Summary</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Assessment Period</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Date</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Total EAD</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Individual CKPN</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Collective CKPN</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Total CKPN</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Coverage %</th>
                        <th class="px-6 py-3 text-center font-semibold text-gray-700">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($reports as $report)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $report['assessment']->period_name }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $report['assessment']->assessment_date->format('d M Y') }}</td>
                            <td class="px-6 py-4 text-right font-semibold">{{ number_format($report['ead'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right text-green-600 font-semibold">{{ number_format($report['individual_ckpn'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right text-indigo-600 font-semibold">{{ number_format($report['collective_ckpn'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right text-blue-600 font-bold text-lg">{{ number_format($report['total_ckpn'] / 1_000_000_000, 2) }}B</td>
                            <td class="px-6 py-4 text-right">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-700">
                                    {{ number_format($report['coverage_ratio'], 2) }}%
                                </span>
                            </td>
                            <td class="px-6 py-4 text-center">
                                <a href="{{ route('reporting.risk-matrix', ['assessment_id' => $report['assessment']->id]) }}" class="text-blue-600 hover:text-blue-800 text-xs font-semibold">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-8 text-center text-gray-500">No completed assessments</td>
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
