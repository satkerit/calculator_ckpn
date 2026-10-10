@extends('layouts.app')

@section('title', 'Snapshot Detail - CKPN Calculator')
@section('page-title', 'Snapshot Detail')

@section('content')
<div class="mb-6 flex items-center gap-4">
    <a href="{{ route('snapshot-history.index') }}" class="text-blue-600 hover:text-blue-900 font-medium flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to List
    </a>
    <h1 class="text-2xl font-bold text-gray-900">Snapshot Detail</h1>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Snapshot Data -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Snapshot Information</h2>
        <div class="space-y-3">
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600 font-medium">Loan Number</span>
                <span class="font-mono text-gray-900">{{ $snapshot->loan_number }}</span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600 font-medium">Office Code</span>
                <span class="text-gray-900">{{ $snapshot->office_code }}</span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600 font-medium">Outstanding Balance</span>
                <span class="font-mono text-gray-900">{{ number_format($snapshot->outstanding_balance, 2) }}</span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600 font-medium">Principal Arrears</span>
                <span class="font-mono text-gray-900">{{ number_format($snapshot->principal_arrears, 2) }}</span>
            </div>
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600 font-medium">Period Snapshot</span>
                <span class="text-gray-900">{{ $snapshot->periode_snapshot }}</span>
            </div>
            @if($snapshot->effective_date)
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-600 font-medium">Effective Date</span>
                    <span class="text-gray-900">{{ $snapshot->effective_date }}</span>
                </div>
            @endif
            @if($snapshot->expired_date)
                <div class="flex justify-between border-b pb-2">
                    <span class="text-gray-600 font-medium">Expired Date</span>
                    <span class="text-gray-900">{{ $snapshot->expired_date }}</span>
                </div>
            @endif
            <div class="flex justify-between border-b pb-2">
                <span class="text-gray-600 font-medium">Created At</span>
                <span class="text-gray-900">{{ $snapshot->created_at->format('d M Y H:i:s') }}</span>
            </div>
            @if($snapshot->updated_at)
                <div class="flex justify-between">
                    <span class="text-gray-600 font-medium">Updated At</span>
                    <span class="text-gray-900">{{ $snapshot->updated_at->format('d M Y H:i:s') }}</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Additional Info -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Additional Details</h2>
        <div class="space-y-3">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-gray-700">
                    <span class="font-medium">ID:</span>
                    <span class="font-mono text-gray-900">{{ $snapshot->id }}</span>
                </p>
            </div>
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <p class="text-sm text-gray-700">
                    <span class="font-medium">Total Exposure:</span>
                    <span class="font-mono text-gray-900">{{ number_format($snapshot->outstanding_balance + $snapshot->principal_arrears, 2) }}</span>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Raw Data -->
<div class="mt-6 bg-white rounded-lg shadow-md p-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Raw Data (JSON)</h2>
    <pre class="bg-gray-50 p-4 rounded-lg overflow-x-auto text-xs">{{ json_encode($snapshot->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
</div>
@endsection
