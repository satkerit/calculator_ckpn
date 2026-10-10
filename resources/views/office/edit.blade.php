@extends('layouts.app')

@section('title', 'Edit Office - CKPN Calculator')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-900 mb-8">Edit Office</h1>

    <form method="POST" action="{{ route('office.update', $office) }}" class="bg-white rounded-lg shadow-md p-6 space-y-6">
        @csrf @method('PUT')

        <div>
            <label for="office_code" class="block text-sm font-semibold text-gray-900 mb-2">
                Office Code <span class="text-red-500">*</span>
            </label>
            <input type="text" id="office_code" name="office_code" required value="{{ old('office_code', $office->office_code) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('office_code')
                <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="office_name" class="block text-sm font-semibold text-gray-900 mb-2">
                Office Name <span class="text-red-500">*</span>
            </label>
            <input type="text" id="office_name" name="office_name" required value="{{ old('office_name', $office->office_name) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
            @error('office_name')
                <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="office_type" class="block text-sm font-semibold text-gray-900 mb-2">
                Office Type <span class="text-red-500">*</span>
            </label>
            <select id="office_type" name="office_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Select type...</option>
                <option value="PUSAT" @selected(old('office_type', $office->office_type) === 'PUSAT')>Pusat (Head Office)</option>
                <option value="CABANG" @selected(old('office_type', $office->office_type) === 'CABANG')>Cabang (Branch)</option>
                <option value="UNIT" @selected(old('office_type', $office->office_type) === 'UNIT')>Unit (Sub Branch)</option>
            </select>
            @error('office_type')
                <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="province" class="block text-sm font-semibold text-gray-900 mb-2">
                    Province <span class="text-red-500">*</span>
                </label>
                <input type="text" id="province" name="province" required value="{{ old('province', $office->province) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('province')
                    <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="city" class="block text-sm font-semibold text-gray-900 mb-2">
                    City <span class="text-red-500">*</span>
                </label>
                <input type="text" id="city" name="city" required value="{{ old('city', $office->city) }}" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                @error('city')
                    <p class="text-sm text-red-600 mt-1 block">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div class="flex items-center">
            <input type="checkbox" id="active" name="active" value="1" @checked(old('active', $office->active)) class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500">
            <label for="active" class="ml-3 text-sm font-semibold text-gray-900">Active</label>
        </div>

        <div class="flex gap-3 pt-4">
            <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Update
            </button>
            <a href="{{ route('office.show', $office) }}" class="inline-flex items-center gap-2 bg-gray-500 text-white px-6 py-2.5 rounded-lg font-semibold hover:bg-gray-600 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                Cancel
            </a>
        </div>
    </form>
</div>
@endsection
