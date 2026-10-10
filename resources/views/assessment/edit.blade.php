@extends('layouts.app')

@section('title', 'Edit Assessment - CKPN Calculator')

@section('content')
<div class="w-full max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Edit Assessment Period</h1>

    <form method="POST" action="{{ route('assessment.update', $assessmentPeriod) }}" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        @csrf @method('PUT')

        <div>
            <label for="period_name" class="block text-sm font-semibold text-gray-900 mb-2">
                Period Name <span class="text-red-500">*</span>
            </label>
            <input type="text" id="period_name" name="period_name" required value="{{ old('period_name', $assessmentPeriod->period_name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('period_name')
                <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="fiscal_month" class="block text-sm font-semibold text-gray-900 mb-2">
                    Month <span class="text-red-500">*</span>
                </label>
                <input type="number" id="fiscal_month" name="fiscal_month" required min="1" max="12" value="{{ old('fiscal_month', $assessmentPeriod->fiscal_month) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('fiscal_month')
                    <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="fiscal_year" class="block text-sm font-semibold text-gray-900 mb-2">
                    Year <span class="text-red-500">*</span>
                </label>
                <input type="number" id="fiscal_year" name="fiscal_year" required min="2000" value="{{ old('fiscal_year', $assessmentPeriod->fiscal_year) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('fiscal_year')
                    <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="assessment_date" class="block text-sm font-semibold text-gray-900 mb-2">
                Assessment Date <span class="text-red-500">*</span>
            </label>
            <input type="date" id="assessment_date" name="assessment_date" required value="{{ old('assessment_date', $assessmentPeriod->assessment_date->format('Y-m-d')) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('assessment_date')
                <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Update
            </button>
            <a href="{{ route('assessment.show', $assessmentPeriod) }}" class="inline-flex items-center gap-2 bg-gray-500 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
