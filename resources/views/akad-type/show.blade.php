@extends('layouts.app')

@section('title', 'Detail Akad - CKPN Calculator')
@section('page-title', 'Detail Tipe Akad')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">{{ $akadType->nama_akad }}</h1>
        <div class="flex gap-2">
            <a href="{{ route('akad-type.edit', $akadType) }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors text-sm">
                Edit
            </a>
            <a href="{{ route('akad-type.index') }}" class="inline-flex items-center gap-2 bg-gray-200 text-gray-800 px-4 py-2 rounded-lg font-medium hover:bg-gray-300 transition-colors text-sm">
                Kembali
            </a>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-md p-8">
        <dl class="space-y-4">
            <div class="grid grid-cols-3 gap-4 py-3 border-b border-gray-100">
                <dt class="text-sm font-semibold text-gray-500">Kode Akad</dt>
                <dd class="col-span-2 text-sm font-mono font-semibold text-blue-600">{{ $akadType->kode_akad }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3 border-b border-gray-100">
                <dt class="text-sm font-semibold text-gray-500">Nama Akad</dt>
                <dd class="col-span-2 text-sm text-gray-900">{{ $akadType->nama_akad }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3 border-b border-gray-100">
                <dt class="text-sm font-semibold text-gray-500">Kategori</dt>
                <dd class="col-span-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                        {{ ucfirst(str_replace('_', ' ', $akadType->kategori)) }}
                    </span>
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3 border-b border-gray-100">
                <dt class="text-sm font-semibold text-gray-500">Aturan EAD</dt>
                <dd class="col-span-2 text-sm font-mono text-gray-700">{{ $akadType->ead_rule_type }}</dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3 border-b border-gray-100">
                <dt class="text-sm font-semibold text-gray-500">Butuh Jatuh Tempo</dt>
                <dd class="col-span-2">
                    @if($akadType->requires_due_date)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">Ya</span>
                    @else
                        <span class="text-gray-400 text-sm">Tidak</span>
                    @endif
                </dd>
            </div>
            <div class="grid grid-cols-3 gap-4 py-3">
                <dt class="text-sm font-semibold text-gray-500">Status</dt>
                <dd class="col-span-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $akadType->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $akadType->active ? 'Aktif' : 'Non-aktif' }}
                    </span>
                </dd>
            </div>
        </dl>
    </div>
</div>
@endsection
