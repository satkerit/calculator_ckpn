@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold">Product Akad Mapping</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('import.product-akad.form') }}" class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm font-medium transition-colors shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                Import Excel
            </a>
            <a href="{{ route('product-akad.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded text-sm font-medium">+ Tambah</a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    <form id="bulk-delete-form" method="POST" action="{{ route('product-akad.bulk-destroy') }}" style="display:none;">
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

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-4 py-3 text-center w-12">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300" onchange="toggleSelectAll(this)">
                    </th>
                    <th class="px-4 py-3 text-left">Product Code</th>
                    <th class="px-4 py-3 text-left">Product Name</th>
                    <th class="px-4 py-3 text-left">Akad Code</th>
                    <th class="px-4 py-3 text-left">EAD Rule Type</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($mappings as $mapping)
                    <tr class="border-b hover:bg-gray-50">
                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="{{ $mapping->id }}" onchange="updateBulkActions()">
                        </td>
                        <td class="px-4 py-3 font-mono">{{ $mapping->product_code }}</td>
                        <td class="px-4 py-3">{{ $mapping->product_name }}</td>
                        <td class="px-4 py-3"><span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">{{ $mapping->akad_code }}</span></td>
                        <td class="px-4 py-3">{{ $mapping->ead_rule_type }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($mapping->active)
                                <span class="text-green-600 font-semibold">✓ Aktif</span>
                            @else
                                <span class="text-red-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center text-sm">
                            <a href="{{ route('product-akad.edit', $mapping) }}" class="text-blue-600 hover:underline">Edit</a> |
                            <form action="{{ route('product-akad.destroy', $mapping) }}" method="POST" class="inline-block" id="delete-form-{{ $mapping->id }}">
                                @csrf @method('DELETE')
                            </form>
                            <button type="button" class="text-red-600 hover:underline" onclick="confirmDeleteMapping({{ $mapping->id }}, '{{ $mapping->product_code }}')">Hapus</button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-3 text-center text-gray-500">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $mappings->links() }}</div>
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
    
    if (window.showConfirmDialog) {
        window.showConfirmDialog({
            title: `Hapus ${ids.length} Mapping?`,
            message: 'Semua mapping yang dipilih akan dihapus.',
            type: 'danger',
            confirmText: 'Ya, Hapus Semua',
            onConfirm: () => submitBulkDelete(ids)
        });
    } else {
        if (confirm(`Yakin ingin menghapus ${ids.length} mapping yang dipilih?`)) {
            submitBulkDelete(ids);
        }
    }
}

function submitBulkDelete(ids) {
    const form = document.getElementById('bulk-delete-form');
    const input = document.getElementById('bulk-delete-ids');
    
    input.value = '';
    
    ids.forEach(id => {
        const field = document.createElement('input');
        field.type = 'hidden';
        field.name = 'ids[]';
        field.value = id;
        form.appendChild(field);
    });
    
    form.submit();
}

function clearSelection() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select-all').checked = false;
    updateBulkActions();
}

function confirmDeleteMapping(id, productCode) {
    if (window.showConfirmDialog) {
        window.showConfirmDialog({
            title: 'Hapus Mapping?',
            message: `Mapping untuk produk <strong>${productCode}</strong> akan dihapus.`,
            type: 'danger',
            confirmText: 'Ya, Hapus',
            onConfirm: () => document.getElementById(`delete-form-${id}`).submit()
        });
    } else {
        if (confirm(`Yakin ingin menghapus mapping untuk produk ${productCode}?`)) {
            document.getElementById(`delete-form-${id}`).submit();
        }
    }
}
</script>
@endsection
