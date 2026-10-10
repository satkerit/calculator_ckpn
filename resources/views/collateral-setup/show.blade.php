@extends('layouts.app')

@section('title', 'Detail Setup Agunan - ' . $collateral->collateral_type)

@section('content')
<div class="max-w-3xl">
    <div class="mb-8 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">{{ $collateral->collateral_type }} - {{ $collateral->collateral_ket }}</h1>
            <p class="text-sm text-gray-600 mt-1">Detail konfigurasi tipe agunan</p>
        </div>
        <div class="inline-flex gap-2">
            <a href="{{ route('collateral-setup.edit', $collateral) }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                Edit
            </a>
            <a href="{{ route('collateral-setup.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                Kembali
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6 space-y-4">
        <div class="border-b pb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase">Tipe Agunan (Code)</p>
            <p class="text-lg font-mono font-bold text-gray-900">{{ $collateral->collateral_type }}</p>
        </div>
        <div class="border-b pb-3">
            <p class="text-xs font-semibold text-gray-500 uppercase">Keterangan Agunan</p>
            <p class="text-lg text-gray-900">{{ $collateral->collateral_ket }}</p>
        </div>
        <div>
            <p class="text-xs font-semibold text-gray-500 uppercase">Status</p>
            <span class="inline-flex items-center px-3 py-1 mt-1 rounded-full text-xs font-semibold {{ $collateral->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                {{ $collateral->active ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </div>
</div>
@endsection
