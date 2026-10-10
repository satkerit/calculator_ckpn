@extends('layouts.app')

@section('title', '{{ isset($role) ? "Edit Role" : "Tambah Role" }} - CKPN Calculator')

@section('content')
<div class="mb-6">
    <a href="{{ route('role.index') }}" class="text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Kembali ke Role
    </a>
</div>

<div class="bg-white rounded-lg shadow-md p-8 max-w-2xl">
    <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ isset($role) ? 'Edit Role: ' . $role->nama_role : 'Tambah Role Baru' }}</h1>

    @if($errors->any())
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded text-red-800 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ isset($role) ? route('role.update', $role) : route('role.store') }}">
        @csrf
        @if(isset($role)) @method('PUT') @endif

        <div class="mb-6">
            <label for="kode_role" class="block text-sm font-semibold text-gray-700 mb-2">Kode Role *</label>
            <input type="text" id="kode_role" name="kode_role" value="{{ old('kode_role', $role->kode_role ?? '') }}" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('kode_role') border-red-500 @enderror"
                   placeholder="e.g., ADMIN, MANAGER" required>
            @error('kode_role')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="nama_role" class="block text-sm font-semibold text-gray-700 mb-2">Nama Role *</label>
            <input type="text" id="nama_role" name="nama_role" value="{{ old('nama_role', $role->nama_role ?? '') }}" 
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('nama_role') border-red-500 @enderror"
                   placeholder="e.g., Administrator, Manager" required>
            @error('nama_role')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label for="deskripsi" class="block text-sm font-semibold text-gray-700 mb-2">Deskripsi</label>
            <textarea id="deskripsi" name="deskripsi" rows="3" 
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent @error('deskripsi') border-red-500 @enderror"
                      placeholder="Deskripsi singkat tentang role ini">{{ old('deskripsi', $role->deskripsi ?? '') }}</textarea>
            @error('deskripsi')
                <p class="text-red-600 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="mb-6">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="active" value="1" {{ old('active', $role->active ?? true) ? 'checked' : '' }} class="rounded border-gray-300">
                <span class="text-sm font-medium text-gray-700">Role Aktif</span>
            </label>
        </div>

        <div class="mb-8">
            <label class="block text-sm font-semibold text-gray-700 mb-4">Permissions</label>
            
            @php
                $groupedPermissions = [];
                foreach($permissions as $perm) {
                    $module = $perm->module ?? 'other';
                    if (!isset($groupedPermissions[$module])) {
                        $groupedPermissions[$module] = [];
                    }
                    $groupedPermissions[$module][] = $perm;
                }
            @endphp

            <div class="space-y-4">
                @foreach($groupedPermissions as $module => $perms)
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center mb-3">
                            <input type="checkbox" id="module-{{ $module }}" class="rounded border-gray-300 module-check" data-module="{{ $module }}" onchange="toggleModule('{{ $module }}')">
                            <label for="module-{{ $module }}" class="ml-2 font-semibold text-gray-700 uppercase text-sm cursor-pointer">{{ $module }}</label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 ml-6">
                            @foreach($perms as $perm)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="permissions[]" value="{{ $perm->id }}" 
                                           class="rounded border-gray-300 perm-check perm-{{ $module }}"
                                           {{ isset($role) && $role->permissions()->where('permission_id', $perm->id)->exists() ? 'checked' : '' }}>
                                    <span class="text-sm text-gray-700">{{ $perm->display_name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="flex gap-3 pt-6 border-t border-gray-200">
            <button type="submit" class="flex-1 bg-blue-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors">
                {{ isset($role) ? 'Update Role' : 'Simpan Role' }}
            </button>
            <a href="{{ route('role.index') }}" class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition-colors text-center">
                Batal
            </a>
        </div>
    </form>
</div>

<script>
function toggleModule(module) {
    const checkbox = document.getElementById('module-' + module);
    document.querySelectorAll('.perm-' + module).forEach(perm => {
        perm.checked = checkbox.checked;
    });
}

document.querySelectorAll('.perm-check').forEach(perm => {
    perm.addEventListener('change', function() {
        const module = this.className.match(/perm-(\w+)/)[1];
        const moduleCheck = document.getElementById('module-' + module);
        const allChecked = document.querySelectorAll('.perm-' + module + ':checked').length === 
                          document.querySelectorAll('.perm-' + module).length;
        moduleCheck.checked = allChecked;
    });
});
</script>
@endsection
