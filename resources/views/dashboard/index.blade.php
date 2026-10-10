@extends('layouts.app')

@section('title', 'Dashboard - CKPN Calculator')

@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-8">
    <!-- KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        <!-- Total Assessments -->
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-blue-100 text-sm">Total Assessments</p>
                    <p class="text-3xl font-bold">{{ $assessmentCount }}</p>
                </div>
                <svg class="w-8 h-8 text-blue-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <p class="text-blue-100 text-xs">
                <span class="font-semibold">{{ ($assessmentByStatus->get('REVIEW')?->total ?? 0) + ($assessmentByStatus->get('APPROVED')?->total ?? 0) + ($assessmentByStatus->get('LOCKED')?->total ?? 0) }}</span> completed
            </p>
        </div>

        <!-- Total Snapshots -->
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-purple-100 text-sm">Total Loan (Snapshot)</p>
                    <p class="text-3xl font-bold">{{ $snapshotCount }}</p>
                </div>
                <svg class="w-8 h-8 text-purple-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-purple-100 text-xs">
                <span class="font-semibold">{{ $classificationStats['with_usage_type'] }}</span> with usage_type
            </p>
        </div>

        <!-- Total EAD -->
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-green-100 text-sm">Total EAD</p>
                    <p class="text-2xl font-bold">{{ number_format($totalEad / 1_000_000_000, 1) }}B</p>
                </div>
                <svg class="w-8 h-8 text-green-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <p class="text-green-100 text-xs">{{ $latestAssessment ? 'as of ' . $latestAssessment->assessment_date->format('d M Y') : 'No data' }}</p>
        </div>

        <!-- Total CKPN -->
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-orange-100 text-sm">Total CKPN</p>
                    <p class="text-2xl font-bold">{{ number_format($totalCkpn / 1_000_000_000, 1) }}B</p>
                </div>
                <svg class="w-8 h-8 text-orange-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-orange-100 text-xs">Risk provision</p>
        </div>

        <!-- Active Users -->
        <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-lg shadow-lg p-6 text-white">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <p class="text-indigo-100 text-sm">Active Users</p>
                    <p class="text-3xl font-bold">{{ $userCount }}</p>
                </div>
                <svg class="w-8 h-8 text-indigo-200" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 12H9m6 0a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
            </div>
            <p class="text-indigo-100 text-xs">System users</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="{{ route('assessment.create') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-all hover:scale-105 flex items-center gap-4">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-800">New Assessment</p>
                <p class="text-xs text-gray-500">Create calculation</p>
            </div>
        </a>

        <a href="{{ route('import.snapshot.form') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-all hover:scale-105 flex items-center gap-4">
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-800">Import Snapshot</p>
                <p class="text-xs text-gray-500">Bulk upload data</p>
            </div>
        </a>

        <a href="{{ route('calculation.module.config') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-all hover:scale-105 flex items-center gap-4">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-800">Run Calculation</p>
                <p class="text-xs text-gray-500">Execute CKPN pipeline</p>
            </div>
        </a>

        <a href="{{ route('user.create') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-all hover:scale-105 flex items-center gap-4">
            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <div>
                <p class="font-semibold text-gray-800">Add User</p>
                <p class="text-xs text-gray-500">Create new account</p>
            </div>
        </a>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 lg:gap-8">
        <!-- Recent Assessments -->
        <div class="xl:col-span-2 bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-lg font-bold text-gray-800">Recent Assessments</h3>
                <a href="{{ route('assessment.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-semibold">View All →</a>
            </div>

            <div class="space-y-4">
                @forelse ($recentAssessments as $assessment)
                    <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-800">{{ $assessment->period_name }}</p>
                            <p class="text-sm text-gray-500">{{ $assessment->assessment_date->format('d M Y') }}</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                @if (in_array($assessment->status, ['REVIEW', 'APPROVED', 'LOCKED'])) bg-green-100 text-green-700
                                @elseif ($assessment->status === 'VALIDATING') bg-yellow-100 text-yellow-700
                                @elseif ($assessment->status === 'CALCULATING') bg-blue-100 text-blue-700
                                @else bg-gray-100 text-gray-700
                                @endif
                            ">
                                {{ $assessment->status }}
                            </span>
                            <a href="{{ route('assessment.show', $assessment) }}" class="text-blue-600 hover:text-blue-800">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                @empty
                    <p class="text-center text-gray-500 py-8">No assessments yet. <a href="{{ route('assessment.create') }}" class="text-blue-600 hover:underline">Create one</a></p>
                @endforelse
            </div>
        </div>

        <!-- Usage Type Stats -->
        <div class="xl:col-span-1 bg-white rounded-lg shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-6">Snapshot Records</h3>

            <div class="space-y-4">
                <!-- With Usage Type -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <p class="text-sm font-medium text-gray-700">With Usage Type</p>
                        <p class="text-sm font-bold text-gray-900">{{ $classificationStats['with_usage_type'] }}</p>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        @php
                            $total = $classificationStats['total'];
                            $pct = $total > 0 ? ($classificationStats['with_usage_type'] / $total) * 100 : 0;
                        @endphp
                        <div class="bg-blue-500 h-2 rounded-full" style="width: {{ $pct }}%"></div>
                    </div>
                </div>

                <!-- Total -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <p class="text-sm font-medium text-gray-700">Total Unique Loans</p>
                        <p class="text-sm font-bold text-gray-900">{{ $classificationStats['total'] }}</p>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-purple-500 h-2 rounded-full" style="width: 100%"></div>
                    </div>
                </div>

                <div class="pt-4 border-t">
                    <p class="text-xs text-gray-500 text-center">Unique loan numbers in snapshot</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Snapshots Table -->
    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="flex justify-between items-center">
                <h3 class="font-bold text-gray-800">Recent Snapshots</h3>
                <a href="{{ route('snapshot-history.index') }}" class="text-blue-600 hover:text-blue-800 text-sm font-semibold">View All →</a>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 border-b">
                    <tr>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Loan Number</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Product</th>
                        <th class="px-6 py-3 text-left font-semibold text-gray-700">Office</th>
                        <th class="px-6 py-3 text-right font-semibold text-gray-700">Outstanding</th>
                        <th class="px-6 py-3 text-center font-semibold text-gray-700">Period</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($recentSnapshots as $snapshot)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 font-medium text-gray-900">{{ $snapshot->loan_number }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $snapshot->product_code ?? '-' }}</td>
                            <td class="px-6 py-4 text-gray-600">{{ $snapshot->office?->office_name ?? $snapshot->office_code ?? '-' }}</td>
                            <td class="px-6 py-4 text-right text-gray-600">{{ number_format($snapshot->outstanding_balance, 0, '.', ',') }}</td>
                            <td class="px-6 py-4 text-center text-gray-500 text-xs">{{ $snapshot->periode_snapshot }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-gray-500">No snapshot data yet</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
