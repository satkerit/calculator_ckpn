@extends('layouts.app')

@section('title', 'Offices - CKPN Calculator')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-gray-900">Offices</h1>
    <div class="flex items-center gap-2">
        <a href="{{ route('import.office.form') }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-emerald-700 transition-colors text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Import Excel
        </a>
        <a href="{{ route('office.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Office
        </a>
    </div>
</div>

@if(session('success'))
    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

<form id="bulk-delete-form" method="POST" action="{{ route('office.bulk-destroy') }}" style="display:none;">
    @csrf @method('DELETE')
    <input type="hidden" name="ids" id="bulk-delete-ids">
</form>

<div id="bulk-actions-bar" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg hidden">
    <div class="flex items-center justify-between">
        <div>
            <span class="text-sm font-medium text-blue-900">
                <span id="selected-count">0</span> data dipilih
            </span>
        </div>
        <div class="flex gap-2">
            <button type="button" id="bulk-delete-btn" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors" onclick="bulkDelete()">
                Hapus Terpilih
            </button>
            <button type="button" id="clear-selection-btn" class="px-4 py-2 bg-gray-400 text-white rounded-lg text-sm font-medium hover:bg-gray-500 transition-colors" onclick="clearSelection()">
                Batal
            </button>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-center w-12">
                    <input type="checkbox" id="select-all" class="rounded border-gray-300" onchange="toggleSelectAll(this)">
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Code</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Name</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Type</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Location</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse ($offices as $office)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-4 text-center">
                        <input type="checkbox" class="row-checkbox rounded border-gray-300" value="{{ $office->id }}" onchange="updateBulkActions()">
                    </td>
                    <td class="px-6 py-4 font-mono text-xs text-gray-900">{{ $office->office_code }}</td>
                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $office->office_name }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $office->office_type }}</span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">{{ $office->city }}, {{ $office->province }}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $office->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $office->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 space-x-1 text-sm">
                        <a href="{{ route('office.show', $office) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            View
                        </a>
                        <a href="{{ route('office.edit', $office) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-green-600 hover:bg-green-50 rounded transition-colors font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Edit
                        </a>
                        <form method="POST" action="{{ route('office.destroy', $office) }}" class="inline-block" id="delete-form-{{ $office->id }}">
                            @csrf @method('DELETE')
                        </form>
                        <button type="button" onclick="confirmDeleteOffice({{ $office->id }}, '{{ $office->office_code }}', '{{ $office->office_name }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-red-600 hover:bg-red-50 rounded transition-colors font-medium">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            Delete
                        </button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <p class="text-gray-500 font-medium">No offices found</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="mt-6">
    {{ $offices->links() }}
</div>

<script>
function toggleSelectAll(checkbox) {
    document.querySelectorAll('.row-checkbox').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const bar = document.getElementById('bulk-actions-bar');
    const count = document.getElementById('selected-count');
    
    count.textContent = checked;
    if (checked > 0) {
        bar.classList.remove('hidden');
    } else {
        bar.classList.add('hidden');
        document.getElementById('select-all').checked = false;
    }
}

function bulkDelete() {
    const ids = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return alert('Pilih minimal satu data');
    
    window.showConfirmDialog({
        title: `Hapus ${ids.length} Data Office?`,
        message: 'Semua office yang dipilih akan dihapus secara permanen dari sistem.',
        detail: `Total: ${ids.length} item(s)`,
        type: 'danger',
        confirmText: 'Ya, Hapus Semua',
        cancelText: 'Batal',
        onConfirm: () => {
            const form = document.getElementById('bulk-delete-form');
            const input = document.getElementById('bulk-delete-ids');
            
            // Clear previous values
            input.value = '';
            
            // Append IDs as array format for Laravel
            ids.forEach(id => {
                const field = document.createElement('input');
                field.type = 'hidden';
                field.name = 'ids[]';
                field.value = id;
                form.appendChild(field);
            });
            
            form.submit();
        }
    });
}

function clearSelection() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select-all').checked = false;
    updateBulkActions();
}

function confirmDeleteOffice(id, officeCode, officeName) {
    window.showConfirmDialog({
        title: 'Hapus Office?',
        message: `Office <strong>${officeName}</strong> akan dihapus secara permanen dari sistem.`,
        detail: `Office Code: ${officeCode}`,
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
