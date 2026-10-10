@extends('layouts.app')

@section('title', 'Assessment Details - CKPN Calculator')

@section('content')
<div class="w-full max-w-4xl">
    <div class="mb-8 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->period_name }}</h1>
        <div class="inline-flex gap-2">
            <a href="{{ route('assessment.edit', $assessmentPeriod) }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            <a href="{{ route('calculation.module.config') }}?assessment_id={{ $assessmentPeriod->id }}" class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-green-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                Run Calculation
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-blue-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Period</p>
            <p class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->fiscal_month }}/{{ $assessmentPeriod->fiscal_year }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-green-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Assessment Date</p>
            <p class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->assessment_date->format('d M Y') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-purple-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</p>
            <p class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->status }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-orange-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Financing Count</p>
            <p class="text-2xl font-bold text-gray-900">{{ $financingCount }}</p>
        </div>
    </div>

    <a href="{{ route('assessment.index') }}" class="inline-flex items-center gap-1 text-blue-600 font-semibold hover:text-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to Assessments
    </a>
</div>
@endsection
