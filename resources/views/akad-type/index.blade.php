@extends('layouts.app')

@section('title', 'Tipe Akad - CKPN Calculator')
@section('page-title', 'Manajemen Akad')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Daftar Tipe Akad</h1>
        <p class="text-sm text-gray-500 mt-1">Konfigurasi akad syariah dan ketentuan kalkulasi EAD</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('import.akad-type.form') }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-emerald-700 transition-colors text-sm shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Import Excel
        </a>
        <a href="{{ route('akad-type.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors text-sm shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Akad
        </a>
    </div>
</div>

<form id="bulk-delete-form" method="POST" action="{{ route('akad-type.bulk-destroy') }}" style="display:none;">
    @csrf @method('DELETE')
    <input type="hidden" name="ids" id="bulk-delete-ids">
</form>

<div id="bulk-actions-bar" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg hidden">
    <div class="flex items-center justify-between">
        <div>
            <span class="text-sm font-medium text-blue-900">
                <span id="selected-count">0</span> akad dipilih
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

<div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-center w-12">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300" onchange="toggleSelectAll(this)">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Nama Akad</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kategori</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Aturan EAD</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Jatuh Tempo</th>
                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($akadTypes as $akad)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-4 text-center">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="{{ $akad->id }}" onchange="updateBulkActions()">
                        </td>
                        <td class="px-6 py-4 font-mono font-semibold text-xs text-blue-600">{{ $akad->kode_akad }}</td>
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $akad->nama_akad }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700 border border-purple-200">
                                {{ ucfirst(str_replace('_', ' ', $akad->kategori)) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-xs text-gray-600 font-mono">
                            {{ $akad->ead_rule_type }}
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($akad->requires_due_date)
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">Wajib</span>
                            @else
                                <span class="text-gray-400 text-xs">-</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $akad->active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $akad->active ? 'Aktif' : 'Non-aktif' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right space-x-1">
                            <a href="{{ route('akad-type.edit', $akad) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors font-medium text-xs">
                                Edit
                            </a>
                            <button type="button" onclick="confirmDelete({{ $akad->id }}, '{{ $akad->kode_akad }}', '{{ $akad->nama_akad }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-red-600 hover:bg-red-50 rounded transition-colors font-medium text-xs">
                                Hapus
                            </button>
                            <form method="POST" action="{{ route('akad-type.destroy', $akad) }}" class="hidden" id="delete-form-{{ $akad->id }}">
                                @csrf @method('DELETE')
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                            Belum ada data tipe akad.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $akadTypes->links() }}
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
        title: `Hapus ${ids.length} Tipe Akad?`,
        message: 'Semua akad yang dipilih akan dihapus secara permanen.',
        detail: `Total: ${ids.length} item`,
        type: 'danger',
        confirmText: 'Ya, Hapus Semua',
        cancelText: 'Batal',
        onConfirm: () => {
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
    });
}

function clearSelection() {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
    document.getElementById('select-all').checked = false;
    updateBulkActions();
}

function confirmDelete(id, kode, nama) {
    window.showConfirmDialog({
        title: 'Hapus Tipe Akad?',
        message: `Akad <strong>${nama}</strong> (${kode}) akan dihapus.`,
        type: 'danger',
        confirmText: 'Hapus',
        cancelText: 'Batal',
        onConfirm: () => {
            document.getElementById(`delete-form-${id}`).submit();
        }
    });
}
</script>
@endsection
