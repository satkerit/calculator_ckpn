@extends('layouts.app')

@section('title', $office->office_name . ' - CKPN Calculator')

@section('content')
<div class="max-w-4xl">
    <div class="mb-8 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ $office->office_name }}</h1>
        <div class="inline-flex gap-2">
            <a href="{{ route('office.edit', $office) }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            <a href="{{ route('office.index') }}" class="inline-flex items-center gap-2 bg-gray-500 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Back
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-blue-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Office Code</p>
            <p class="text-lg font-mono font-bold text-gray-900">{{ $office->office_code }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-green-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Office Type</p>
            <p class="text-lg">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                    {{ $office->office_type }}
                </span>
            </p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-orange-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Location</p>
            <p class="text-lg font-bold text-gray-900">{{ $office->city }}, {{ $office->province }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-purple-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</p>
            <p class="text-lg">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $office->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $office->active ? 'Active' : 'Inactive' }}
                </span>
            </p>
        </div>
    </div>
</div>
@endsection
