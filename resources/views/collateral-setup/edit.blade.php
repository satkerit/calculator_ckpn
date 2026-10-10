@extends('layouts.app')

@section('title', 'Edit Setup Agunan - CKPN Calculator')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Edit Setup Agunan</h1>

    <form method="POST" action="{{ route('collateral-setup.update', $collateral) }}" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        @csrf @method('PUT')

        <div>
            <label class="block text-sm font-semibold text-gray-900 mb-2">Tipe Agunan (Code)</label>
            <input type="text" disabled value="{{ $collateral->collateral_type }}" class="w-full px-4 py-2 bg-gray-100 border border-gray-300 rounded-lg text-gray-600 cursor-not-allowed">
            <p class="text-xs text-gray-500 mt-1">Kode tipe agunan adalah primary key dan tidak dapat diubah.</p>
        </div>

        <div>
            <label for="collateral_ket" class="block text-sm font-semibold text-gray-900 mb-2">
                Keterangan Agunan <span class="text-red-500">*</span>
            </label>
            <input type="text" id="collateral_ket" name="collateral_ket" required value="{{ old('collateral_ket', $collateral->collateral_ket) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('collateral_ket')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center">
            <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $collateral->active)) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
            <label for="active" class="ml-3 text-sm font-semibold text-gray-900">Active</label>
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                Perbarui
            </button>
            <a href="{{ route('collateral-setup.index') }}" class="bg-gray-500 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
