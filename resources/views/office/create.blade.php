@extends('layouts.app')

@section('title', 'Create Office - CKPN Calculator')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">Create New Office</h1>

    <form method="POST" action="{{ route('office.store') }}" class="bg-white rounded-lg shadow-md p-8 space-y-6">
        @csrf

        <div>
            <label for="office_code" class="block text-sm font-semibold text-gray-900 mb-2">Office Code <span class="text-red-500">*</span></label>
            <input type="text" id="office_code" name="office_code" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="e.g., PUSAT001" value="{{ old('office_code') }}">
            @error('office_code')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="office_name" class="block text-sm font-semibold text-gray-900 mb-2">Office Name <span class="text-red-500">*</span></label>
            <input type="text" id="office_name" name="office_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('office_name') }}">
            @error('office_name')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div>
            <label for="office_type" class="block text-sm font-semibold text-gray-900 mb-2">Office Type <span class="text-red-500">*</span></label>
            <select id="office_type" name="office_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                <option value="">-- Select Office Type --</option>
                <option value="PUSAT" @selected(old('office_type') === 'PUSAT')>Pusat (Head Office)</option>
                <option value="CABANG" @selected(old('office_type') === 'CABANG')>Cabang (Branch)</option>
                <option value="UNIT" @selected(old('office_type') === 'UNIT')>Unit (Sub Branch)</option>
            </select>
            @error('office_type')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="province" class="block text-sm font-semibold text-gray-900 mb-2">Province <span class="text-red-500">*</span></label>
                <input type="text" id="province" name="province" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('province') }}">
                @error('province')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
            </div>
            <div>
                <label for="city" class="block text-sm font-semibold text-gray-900 mb-2">City <span class="text-red-500">*</span></label>
                <input type="text" id="city" name="city" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="{{ old('city') }}">
                @error('city')<span class="text-sm text-red-600 mt-1 block">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="flex items-center gap-3">
            <input type="checkbox" id="active" name="active" value="1" checked class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
            <label for="active" class="text-sm font-medium text-gray-900">Active</label>
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create
            </button>
            <a href="{{ route('office.index') }}" class="inline-flex items-center gap-2 bg-gray-200 text-gray-800 px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-300 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
