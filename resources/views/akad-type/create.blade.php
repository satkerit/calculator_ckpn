@extends('layouts.app')

@section('title', 'Tambah Akad - CKPN Calculator')
@section('page-title', 'Tambah Tipe Akad')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Tambah Tipe Akad Baru</h1>

    <form method="POST" action="{{ route('akad-type.store') }}" class="bg-white rounded-lg shadow-md p-8 space-y-6">
        @csrf

        <div>
            <label for="kode_akad" class="block text-sm font-semibold text-gray-900 mb-2">Kode Akad <span class="text-red-500">*</span></label>
            <input type="text" id="kode_akad" name="kode_akad" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="cth: MURABAHAH" value="{{ old('kode_akad') }}">
            @error('kode_akad')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="nama_akad" class="block text-sm font-semibold text-gray-900 mb-2">Nama Akad <span class="text-red-500">*</span></label>
            <input type="text" id="nama_akad" name="nama_akad" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="cth: Murabahah" value="{{ old('nama_akad') }}">
            @error('nama_akad')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="kategori" class="block text-sm font-semibold text-gray-900 mb-2">Kategori <span class="text-red-500">*</span></label>
            <select id="kategori" name="kategori" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                <option value="">-- Pilih Kategori --</option>
                <option value="jual_beli" @selected(old('kategori') === 'jual_beli')>Jual Beli</option>
                <option value="bagi_hasil" @selected(old('kategori') === 'bagi_hasil')>Bagi Hasil</option>
                <option value="sewa" @selected(old('kategori') === 'sewa')>Sewa</option>
                <option value="pinjaman" @selected(old('kategori') === 'pinjaman')>Pinjaman</option>
            </select>
            @error('kategori')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="ead_rule_type" class="block text-sm font-semibold text-gray-900 mb-2">Aturan EAD <span class="text-red-500">*</span></label>
            <select id="ead_rule_type" name="ead_rule_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                <option value="">-- Pilih Aturan EAD --</option>
                <option value="outstanding_balance" @selected(old('ead_rule_type') === 'outstanding_balance')>Outstanding Balance</option>
                <option value="principal_arrears_conditional" @selected(old('ead_rule_type') === 'principal_arrears_conditional')>Principal Arrears (Conditional)</option>
                <option value="zero_if_not_expired" @selected(old('ead_rule_type') === 'zero_if_not_expired')>Zero If Not Expired</option>
            </select>
            @error('ead_rule_type')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div class="flex items-center gap-6">
            <div class="flex items-center gap-3">
                <input type="checkbox" id="requires_due_date" name="requires_due_date" value="1" class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500" {{ old('requires_due_date') ? 'checked' : '' }}>
                <label for="requires_due_date" class="text-sm font-medium text-gray-900">Butuh Tanggal Jatuh Tempo</label>
            </div>
            <div class="flex items-center gap-3">
                <input type="checkbox" id="active" name="active" value="1" checked class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
                <label for="active" class="text-sm font-medium text-gray-900">Aktif</label>
            </div>
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Simpan
            </button>
            <a href="{{ route('akad-type.index') }}" class="inline-flex items-center gap-2 bg-gray-200 text-gray-800 px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Batal
            </a>
        </div>
    </form>
</div>
@endsection
