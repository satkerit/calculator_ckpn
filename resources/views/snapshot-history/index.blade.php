@extends('layouts.app')

@section('title', 'Snapshot History - CKPN Calculator')
@section('page-title', 'Snapshot History')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-gray-900">Snapshot Historical Data</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('import.snapshot.form') }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Upload Snapshot
        </a>
        <form method="POST" action="{{ route('snapshot-history.export') }}" class="inline">
            @csrf
            <input type="hidden" name="search" value="{{ request('search') }}">
            <input type="hidden" name="office_code" value="{{ request('office_code') }}">
            <input type="hidden" name="period_from" value="{{ request('period_from') }}">
            <input type="hidden" name="period_to" value="{{ request('period_to') }}">
            <button type="submit" class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-green-700 transition-colors text-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Export CSV
            </button>
        </form>
    </div>
</div>

<!-- Search & Filter -->
<div class="bg-white rounded-lg shadow-md p-4 mb-6">
    <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Search (Loan/Office)</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Enter loan or office code" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Office</label>
            <select name="office_code" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">All Offices</option>
                @foreach($offices as $code => $name)
                    <option value="{{ $code }}" @if(request('office_code') == $code) selected @endif>{{ $name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Period From <span class="text-gray-400 font-normal">(YYYYMM)</span></label>
            <input type="month" name="period_from"
                value="{{ request('period_from') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Period To <span class="text-gray-400 font-normal">(YYYYMM)</span></label>
            <input type="month" name="period_to"
                value="{{ request('period_to') }}"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">Search</button>
            <a href="{{ route('snapshot-history.index') }}" class="flex-1 px-4 py-2 bg-gray-400 text-white rounded-lg text-sm font-medium hover:bg-gray-500 transition-colors text-center">Reset</a>
        </div>
    </form>
</div>

<!-- Table -->
<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Loan Number</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Office Code</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Outstanding Balance</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Principal Arrears</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Period</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Created</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @if($snapshots->count())
                    @foreach($snapshots as $snapshot)
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 font-mono text-xs text-gray-900">{{ $snapshot->loan_number }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $snapshot->office_code }}</td>
                            <td class="px-6 py-4 text-right font-mono text-xs text-gray-900">{{ number_format($snapshot->outstanding_balance, 2) }}</td>
                            <td class="px-6 py-4 text-right font-mono text-xs text-gray-900">{{ number_format($snapshot->principal_arrears, 2) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $snapshot->periode_snapshot }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ $snapshot->created_at->format('d M Y H:i') }}</td>
                            <td class="px-6 py-4 text-sm space-x-1">
                                @if($snapshot->id)
                                    <a href="{{ route('snapshot-history.show', $snapshot->id) }}" class="text-blue-600 hover:text-blue-900 font-medium">View</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <p class="text-gray-500 font-medium">No snapshot data found</p>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<div class="mt-6">
    {{ $snapshots->links() }}
</div>
@endsection
