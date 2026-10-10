@extends('layouts.app')

@section('title', 'Create Assessment - CKPN Calculator')

@section('content')
<div class="w-full max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Create New Assessment Period</h1>

    <form method="POST" action="{{ route('assessment.store') }}" class="bg-white rounded-lg shadow-md p-8 space-y-6">
        @csrf

        <div>
            <label for="period_name" class="block text-sm font-semibold text-gray-900 mb-2">Period Name <span class="text-red-500">*</span></label>
            <input type="text" id="period_name" name="period_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="e.g., September 2026" value="{{ old('period_name') }}">
            @error('period_name')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="fiscal_month" class="block text-sm font-semibold text-gray-900 mb-2">Month <span class="text-red-500">*</span></label>
                <input type="number" id="fiscal_month" name="fiscal_month" required min="1" max="12" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('fiscal_month') }}">
                @error('fiscal_month')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
            </div>
            <div>
                <label for="fiscal_year" class="block text-sm font-semibold text-gray-900 mb-2">Year <span class="text-red-500">*</span></label>
                <input type="number" id="fiscal_year" name="fiscal_year" required min="2000" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('fiscal_year', now()->year) }}">
                @error('fiscal_year')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
            </div>
        </div>

        <div>
            <label for="assessment_date" class="block text-sm font-semibold text-gray-900 mb-2">Assessment Date <span class="text-red-500">*</span></label>
            <input type="date" id="assessment_date" name="assessment_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('assessment_date') }}">
            @error('assessment_date')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create
            </button>
            <a href="{{ route('assessment.index') }}" class="inline-flex items-center gap-2 bg-gray-200 text-gray-800 px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
