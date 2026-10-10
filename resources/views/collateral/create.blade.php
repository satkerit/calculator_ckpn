@extends('layouts.app')

@section('title', 'Tambah Collateral - CKPN Calculator')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Tambah Collateral</h1>

    <form method="POST" action="{{ route('collateral.store') }}" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        @csrf

        <div>
            <label for="loan_number" class="block text-sm font-semibold text-gray-900 mb-2">
                Nomor Rekening Pembiayaan (Loan Number) <span class="text-red-500">*</span>
            </label>
            <input type="text" id="loan_number" name="loan_number" required value="{{ old('loan_number') }}" placeholder="Contoh: L001" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('loan_number')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="collateral_code" class="block text-sm font-semibold text-gray-900 mb-2">
                    Kode Agunan
                </label>
                <input type="text" id="collateral_code" name="collateral_code" value="{{ old('collateral_code') }}" placeholder="Contoh: CLT001" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('collateral_code')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="collateral_order" class="block text-sm font-semibold text-gray-900 mb-2">
                    Urutan Agunan <span class="text-red-500">*</span>
                </label>
                <input type="number" id="collateral_order" name="collateral_order" required min="1" value="{{ old('collateral_order', 1) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('collateral_order')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="collateral_type" class="block text-sm font-semibold text-gray-900 mb-2">
                Tipe Agunan (Collateral Setup) <span class="text-red-500">*</span>
            </label>
            <select id="collateral_type" name="collateral_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">-- Pilih Tipe Agunan --</option>
                @foreach($setups as $setup)
                    <option value="{{ $setup->collateral_type }}" @selected(old('collateral_type') == $setup->collateral_type)>
                        {{ $setup->collateral_type }} - {{ $setup->collateral_ket }}
                    </option>
                @endforeach
            </select>
            @error('collateral_type')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="liquidity_value" class="block text-sm font-semibold text-gray-900 mb-2">
                    Nilai Likuidasi (Rp)
                </label>
                <input type="number" step="0.01" id="liquidity_value" name="liquidity_value" value="{{ old('liquidity_value', 0) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('liquidity_value')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="estimated_value" class="block text-sm font-semibold text-gray-900 mb-2">
                    Nilai Wajar / Pasar (Rp)
                </label>
                <input type="number" step="0.01" id="estimated_value" name="estimated_value" value="{{ old('estimated_value', 0) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('estimated_value')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="valuation_date" class="block text-sm font-semibold text-gray-900 mb-2">
                Tanggal Penilaian / Taksasi
            </label>
            <input type="date" id="valuation_date" name="valuation_date" value="{{ old('valuation_date') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('valuation_date')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center">
            <input type="checkbox" id="active_flag" name="active_flag" value="1" @checked(old('active_flag', true)) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
            <label for="active_flag" class="ml-3 text-sm font-semibold text-gray-900">Active</label>
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                Simpan
            </button>
            <a href="{{ route('collateral.index') }}" class="bg-gray-500 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
