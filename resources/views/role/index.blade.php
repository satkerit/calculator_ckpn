@extends('layouts.app')

@section('title', 'Role Management - CKPN Calculator')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Role Management</h1>
        <p class="text-sm text-gray-600 mt-1">Kelola role dan hak akses pengguna</p>
    </div>
    <a href="{{ route('role.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Role
    </a>
</div>

@if(session('success'))
    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg text-red-800 text-sm">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<!-- Bulk Actions Bar -->
<div id="bulk-actions-bar" class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4 hidden">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-red-900">
            <span id="selected-count">0</span> role dipilih
        </span>
        <button type="button" onclick="bulkDelete()" class="inline-flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Hapus Terpilih
        </button>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($roles as $role)
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <div class="flex items-start justify-between mb-4">
                @if($role->users()->count() === 0)
                    <input type="checkbox" class="row-checkbox mt-1" value="{{ $role->id }}" onchange="updateBulkActionsBar()">
                @else
                    <div class="w-4"></div>
                @endif
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">{{ $role->nama_role }}</h3>
                    <p class="text-xs text-gray-500 font-mono">{{ $role->kode_role }}</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $role->active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                    {{ $role->active ? 'Active' : 'Inactive' }}
                </span>
            </div>

            @if($role->deskripsi)
                <p class="text-sm text-gray-600 mb-4">{{ $role->deskripsi }}</p>
            @endif

            <div class="mb-4">
                <p class="text-xs font-semibold text-gray-700 uppercase mb-2">Permissions ({{ $role->permissions()->count() }})</p>
                <div class="flex flex-wrap gap-1">
                    @forelse($role->permissions()->limit(5)->get() as $perm)
                        <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">{{ $perm->display_name }}</span>
                    @empty
                        <span class="text-xs text-gray-500">Tidak ada permission</span>
                    @endforelse
                    @if($role->permissions()->count() > 5)
                        <span class="text-xs bg-gray-100 text-gray-600 px-2 py-1 rounded">+{{ $role->permissions()->count() - 5 }} lainnya</span>
                    @endif
                </div>
            </div>

            <div class="flex gap-2 pt-4 border-t border-gray-200">
                <a href="{{ route('role.edit', $role) }}" class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-2 bg-blue-50 text-blue-600 hover:bg-blue-100 rounded text-sm font-medium transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit
                </a>
                @if($role->users()->count() === 0)
                    <form method="POST" action="{{ route('role.destroy', $role) }}" class="flex-1" id="delete-form-{{ $role->id }}">
                        @csrf @method('DELETE')
                    </form>
                    <button type="button" onclick="confirmDeleteRole({{ $role->id }}, '{{ $role->nama_role }}')" class="w-full inline-flex items-center justify-center gap-1 px-3 py-2 bg-red-50 text-red-600 hover:bg-red-100 rounded text-sm font-medium transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus
                    </button>
                @else
                    <button type="button" disabled class="flex-1 inline-flex items-center justify-center gap-1 px-3 py-2 bg-gray-100 text-gray-400 rounded text-sm font-medium cursor-not-allowed" title="Cannot delete role with users">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Hapus
                    </button>
                @endif
            </div>
            <p class="text-xs text-gray-400 mt-3 text-center">{{ $role->users()->count() }} user(s)</p>
        </div>
    @empty
        <div class="col-span-full text-center py-12">
            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            <p class="text-gray-500 font-medium">Belum ada role</p>
        </div>
    @endforelse
</div>

<!-- Bulk Delete Form -->
<form method="POST" action="{{ route('role.bulk-destroy') }}" id="bulk-delete-form" style="display:none;">
    @csrf @method('DELETE')
</form>

<script>
function updateBulkActionsBar() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const count = checkboxes.length;
    const bar = document.getElementById('bulk-actions-bar');
    const countSpan = document.getElementById('selected-count');
    
    if (count > 0) {
        bar.classList.remove('hidden');
        countSpan.textContent = count;
    } else {
        bar.classList.add('hidden');
    }
}

function bulkDelete() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value, 10));
    
    if (ids.length === 0) return;
    
    window.showConfirmDialog({
        title: 'Hapus Role Terpilih?',
        message: `<strong>${ids.length} role</strong> akan dihapus secara permanen dari sistem.`,
        detail: `${ids.length} role dipilih`,
        type: 'danger',
        confirmText: 'Ya, Hapus Semua',
        cancelText: 'Batal',
        onConfirm: () => {
            const form = document.getElementById('bulk-delete-form');
            ids.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'ids[]';
                input.value = id;
                form.appendChild(input);
            });
            form.submit();
        }
    });
}

function confirmDeleteRole(id, roleName) {
    window.showConfirmDialog({
        title: 'Hapus Role?',
        message: `Role <strong>${roleName}</strong> akan dihapus secara permanen dari sistem.`,
        detail: `Role: ${roleName}`,
        type: 'danger',
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        onConfirm: () => {
            document.getElementById(`delete-form-${id}`).submit();
        }
    });
}
</script>
@endsection
