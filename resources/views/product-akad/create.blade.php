@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-6">Tambah Product Akad Mapping</h1>

    <div class="bg-white rounded shadow p-6 max-w-2xl">
        <form action="{{ route('product-akad.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Product Code *</label>
                <input type="text" name="product_code" class="w-full border rounded px-3 py-2 @error('product_code') border-red-500 @enderror" 
                    value="{{ old('product_code') }}" placeholder="e.g., 03, 55, 72" required>
                @error('product_code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Product Name *</label>
                <input type="text" name="product_name" class="w-full border rounded px-3 py-2 @error('product_name') border-red-500 @enderror" 
                    value="{{ old('product_name') }}" placeholder="e.g., Pembiayaan Murabahah" required>
                @error('product_name') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Akad Type *</label>
                <select name="akad_code" class="w-full border rounded px-3 py-2 @error('akad_code') border-red-500 @enderror" required>
                    <option value="">-- Pilih Akad --</option>
                    @foreach($akadTypes as $akad)
                        <option value="{{ $akad->kode_akad }}" @selected(old('akad_code') === $akad->kode_akad)>{{ $akad->kode_akad }} - {{ $akad->nama_akad }}</option>
                    @endforeach
                </select>
                @error('akad_code') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">EAD Rule Type *</label>
                <select name="ead_rule_type" class="w-full border rounded px-3 py-2 @error('ead_rule_type') border-red-500 @enderror" required>
                    <option value="">-- Pilih Rule --</option>
                    <option value="MURABAHAH" @selected(old('ead_rule_type') === 'MURABAHAH')>MURABAHAH</option>
                    <option value="MULTIJASA" @selected(old('ead_rule_type') === 'MULTIJASA')>MULTIJASA</option>
                    <option value="MUSYARAKAH" @selected(old('ead_rule_type') === 'MUSYARAKAH')>MUSYARAKAH</option>
                    <option value="IMBT" @selected(old('ead_rule_type') === 'IMBT')>IMBT</option>
                </select>
                @error('ead_rule_type') <span class="text-red-600 text-sm">{{ $message }}</span> @enderror
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold mb-2">Keterangan</label>
                <textarea name="description" class="w-full border rounded px-3 py-2" rows="3" placeholder="Deskripsi mapping ini">{{ old('description') }}</textarea>
            </div>

            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Simpan</button>
                <a href="{{ route('product-akad.index') }}" class="bg-gray-400 text-white px-4 py-2 rounded hover:bg-gray-500">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection
