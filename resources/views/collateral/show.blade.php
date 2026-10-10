@extends('layouts.app')

@section('title', 'Detail Collateral - CKPN Calculator')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Detail Collateral</h1>
            <p class="text-sm text-gray-500 font-mono">No. Rekening: {{ $collateral->loan_number }} | Agunan ke-{{ $collateral->collateral_order }}</p>
        </div>
        <div class="inline-flex gap-2">
            <a href="{{ route('collateral.edit', $collateral) }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            <a href="{{ route('collateral.index') }}" class="inline-flex items-center gap-2 bg-gray-500 text-white px-4 py-2.5 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Kembali
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-blue-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Nomor Pembiayaan (Loan Number)</p>
            <p class="text-lg font-mono font-bold text-gray-900">{{ $collateral->loan_number }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-indigo-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Kode / No. Urut Agunan</p>
            <p class="text-lg font-mono font-bold text-gray-900">{{ $collateral->collateral_code ?: '-' }} <span class="text-sm font-normal text-gray-500">(Urutan: {{ $collateral->collateral_order }})</span></p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-cyan-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Tipe / Jenis Agunan</p>
            <p class="text-lg font-bold text-gray-900">{{ $collateral->collateral_type }} - {{ $collateral->collateralSetup->collateral_ket ?? 'N/A' }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-purple-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Status Agunan</p>
            <p class="text-lg">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $collateral->active_flag ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                    {{ $collateral->active_flag ? 'Active' : 'Inactive' }}
                </span>
            </p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-green-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Nilai Pasar / Taksasi (Estimated Value)</p>
            <p class="text-xl font-bold text-green-700">Rp {{ number_format($collateral->estimated_value, 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-md p-6 border-t-4 border-emerald-600">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Nilai Likuidasi (Liquidity Value)</p>
            <p class="text-xl font-bold text-emerald-800">Rp {{ number_format($collateral->liquidity_value, 2, ',', '.') }}</p>
        </div>
        <div class="col-span-1 md:col-span-2 bg-white rounded-lg shadow-md p-6 border-t-4 border-gray-400">
            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Tanggal Valuasi / Penilaian</p>
            <p class="text-gray-900 font-medium">{{ $collateral->valuation_date ? date('d F Y', strtotime($collateral->valuation_date)) : 'Belum diisi' }}</p>
        </div>
    </div>
</div>
@endsection
