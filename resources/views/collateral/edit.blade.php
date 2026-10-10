@extends('layouts.app')

@section('title', 'Edit Collateral - CKPN Calculator')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-6 flex justify-between items-center">
        <h1 class="text-2xl font-bold text-gray-900">Edit Collateral</h1>
        <a href="{{ route('collateral.index') }}" class="inline-flex items-center gap-2 text-gray-600 hover:text-gray-900 text-sm font-semibold">
            ← Kembali ke Daftar
        </a>
    </div>

    <form method="POST" action="{{ route('collateral.update', $collateral) }}" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-semibold text-gray-900 mb-1">
                Nomor Rekening Pembiayaan
            </label>
            <input type="text" value="{{ $collateral->loan_number }}" disabled class="w-full px-4 py-2 border border-gray-200 bg-gray-50 rounded-lg text-gray-600 font-mono cursor-not-allowed">
            <p class="text-xs text-gray-500 mt-1">Nomor pembiayaan tidak dapat diubah.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="collateral_code" class="block text-sm font-semibold text-gray-900 mb-1">
                    Kode Collateral
                </label>
                <input type="text" id="collateral_code" name="collateral_code" value="{{ old('collateral_code', $collateral->collateral_code) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono">
                @error('collateral_code')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="collateral_order" class="block text-sm font-semibold text-gray-900 mb-1">
                    No. Urut Agunan <span class="text-red-500">*</span>
                </label>
                <input type="number" id="collateral_order" name="collateral_order" required min="1" value="{{ old('collateral_order', $collateral->collateral_order) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('collateral_order')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="collateral_type" class="block text-sm font-semibold text-gray-900 mb-1">
                Tipe Agunan (Collateral Type) <span class="text-red-500">*</span>
            </label>
            <select id="collateral_type" name="collateral_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @foreach($setups as $setup)
                    <option value="{{ $setup->collateral_type }}" {{ old('collateral_type', $collateral->collateral_type) == $setup->collateral_type ? 'selected' : '' }}>
                        {{ $setup->collateral_type }} - {{ $setup->collateral_ket }}
                    </option>
                @endforeach
            </select>
            @error('collateral_type')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="estimated_value" class="block text-sm font-semibold text-gray-900 mb-1">
                    Nilai Taksasi Pasar (Estimated)
                </label>
                <input type="number" id="estimated_value" name="estimated_value" step="0.01" min="0" value="{{ old('estimated_value', $collateral->estimated_value) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('estimated_value')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="liquidity_value" class="block text-sm font-semibold text-gray-900 mb-1">
                    Nilai Likuidasi (Liquidity Value)
                </label>
                <input type="number" id="liquidity_value" name="liquidity_value" step="0.01" min="0" value="{{ old('liquidity_value', $collateral->liquidity_value) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('liquidity_value')
                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="valuation_date" class="block text-sm font-semibold text-gray-900 mb-1">
                Tanggal Penilaian / Valuasi
            </label>
            <input type="date" id="valuation_date" name="valuation_date" value="{{ old('valuation_date', $collateral->valuation_date ? date('Y-m-d', strtotime($collateral->valuation_date)) : '') }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('valuation_date')
                <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center gap-2">
            <input type="checkbox" id="active_flag" name="active_flag" value="1" {{ old('active_flag', $collateral->active_flag) ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            <label for="active_flag" class="text-sm font-medium text-gray-700">Agunan Aktif (Active)</label>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <a href="{{ route('collateral.index') }}" class="px-5 py-2.5 bg-gray-100 text-gray-700 font-semibold rounded-lg hover:bg-gray-200 transition-colors">Batal</a>
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors">Simpan Perubahan</button>
        </div>
    </form>
</div>
@endsection
