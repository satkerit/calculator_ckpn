@extends('layouts.app')

@section('title', 'Detail Assessment - CKPN Calculator')

@section('page-title', 'Detail Assessment')

@section('content')
<div class="w-full max-w-4xl">
    <div class="mb-8 flex justify-between items-center">
        <h2 class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->period_name }}</h2>
        <div class="inline-flex gap-2">
            <a href="{{ route('assessment.edit', $assessmentPeriod) }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Ubah
            </a>
            <a href="{{ route('calculation.module.config') }}?assessment_id={{ $assessmentPeriod->id }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-emerald-700 transition-colors">
                <svg class="w-4 h-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Buka Konfigurasi
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Periode</p>
            <p class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->fiscal_month }}/{{ $assessmentPeriod->fiscal_year }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Tanggal Assessment</p>
            <p class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->assessment_date->format('d M Y') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status</p>
            <p class="text-2xl font-bold text-gray-900">{{ $assessmentPeriod->status }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Jumlah Pembiayaan</p>
            <p class="text-2xl font-bold text-gray-900">{{ $financingCount }}</p>
        </div>
    </div>

    <a href="{{ route('assessment.index') }}" class="inline-flex items-center gap-1 text-blue-600 font-semibold hover:text-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke Assessment
    </a>
</div>
@endsection
