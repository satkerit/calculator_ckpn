@extends('layouts.app')

@section('title', 'Setup Collateral - CKPN Calculator')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Setup Collateral</h1>
        <p class="text-sm text-gray-600 mt-1">Daftar jenis dan parameter tipe agunan</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('import.collateral_setup.form') }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-emerald-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Import Excel
        </a>
        <a href="{{ route('collateral-setup.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Setup
        </a>
    </div>
</div>

@if(session('success'))
    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

<!-- Bulk Actions Bar -->
<div id="bulk-actions-bar" class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4 hidden">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-red-900">
            <span id="selected-count">0</span> agunan dipilih
        </span>
        <button type="button" onclick="bulkDelete()" class="inline-flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Hapus Terpilih
        </button>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider w-12">
                    <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Tipe Agunan (Code)</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Keterangan Agunan</th>
                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse ($collaterals as $setup)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 text-center">
                        <input type="checkbox" class="row-checkbox" value="{{ $setup->collateral_type }}" onchange="updateBulkActionsBar()">
                    </td>
                    <td class="px-6 py-4 font-mono font-semibold text-gray-900">{{ $setup->collateral_type }}</td>
                    <td class="px-6 py-4 text-gray-800 font-medium">{{ $setup->collateral_ket }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $setup->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $setup->active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <a href="{{ route('collateral-setup.show', $setup->collateral_type) }}" class="text-blue-600 hover:text-blue-800 font-medium">Detail</a>
                        <a href="{{ route('collateral-setup.edit', $setup->collateral_type) }}" class="text-green-600 hover:text-green-800 font-medium">Edit</a>
                        <button type="button" onclick="confirmDeleteCollateralSetup({{ json_encode($setup->collateral_type) }}, {{ json_encode($setup->collateral_type) }})" class="text-red-600 hover:text-red-800 font-medium">
                            Hapus
                        </button>
                    </td>

                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        Belum ada data setup agunan.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $collaterals->links() }}
</div>

<!-- Bulk Delete Form -->
<form method="POST" action="{{ route('collateral-setup.bulk-destroy') }}" id="bulk-delete-form" style="display:none;">
    @csrf @method('DELETE')
</form>

<!-- Single Delete Forms -->
@foreach ($collaterals as $setup)
    <form method="POST" action="{{ route('collateral-setup.destroy', $setup->collateral_type) }}" id="delete-form-{{ $setup->collateral_type }}" style="display:none;">
        @csrf @method('DELETE')
    </form>
@endforeach

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
    
    // Uncheck select-all if not all selected
    const allCheckboxes = document.querySelectorAll('.row-checkbox');
    document.getElementById('select-all').checked = (count === allCheckboxes.length && count > 0);
}

function toggleSelectAll(checkbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateBulkActionsBar();
}

function bulkDelete() {
    const checkboxes = document.querySelectorAll('.row-checkbox:checked');
    const ids = Array.from(checkboxes).map(cb => cb.value);
    
    if (ids.length === 0) return;
    
    window.showConfirmDialog({
        title: 'Hapus Agunan Terpilih?',
        message: '<strong>' + ids.length + ' setup agunan</strong> akan dihapus secara permanen.',
        detail: ids.length + ' agunan dipilih: ' + ids.join(', '),
        type: 'danger',
        confirmText: 'Ya, Hapus Semua',
        cancelText: 'Batal',
        onConfirm: () => {
            const form = document.getElementById('bulk-delete-form');
            // Clear previous inputs
            form.querySelectorAll('input[name="ids[]"]').forEach(input => input.remove());
            // Append fresh inputs
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

function confirmDeleteCollateralSetup(collateralType, displayName) {
    window.showConfirmDialog({
        title: 'Hapus Setup Agunan?',
        message: 'Setup agunan tipe <strong>' + displayName + '</strong> akan dihapus secara permanen.',
        detail: 'Collateral Type: ' + displayName,
        type: 'danger',
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        onConfirm: () => {
            document.getElementById('delete-form-' + collateralType).submit();
        }
    });
}
</script>
@endsection
