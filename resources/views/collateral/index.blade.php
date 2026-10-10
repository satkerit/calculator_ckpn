@extends('layouts.app')

@section('title', 'Daftar Collateral - CKPN Calculator')

@section('content')
<div class="mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Daftar Collateral</h1>
        <p class="text-sm text-gray-600 mt-1">Daftar jaminan/agunan per fasilitas pembiayaan</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('import.collateral.form') }}" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-emerald-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
            Import Excel
        </a>
        <a href="{{ route('collateral.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Tambah Collateral
        </a>
    </div>
</div>

@if(session('success'))
    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif

<form id="bulk-delete-form" method="POST" action="{{ route('collateral.bulk-destroy') }}" style="display:none;">
    @csrf @method('DELETE')
    <input type="hidden" name="ids" id="bulk-delete-ids">
</form>

<div class="mb-4 bg-white p-4 rounded-lg shadow-sm">
    <form method="GET" action="{{ route('collateral.index') }}" class="flex gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari Loan Number, Kode Agunan, atau Tipe..." class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
        <button type="submit" class="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">Cari</button>
        @if(request('search'))
            <a href="{{ route('collateral.index') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300">Reset</a>
        @endif
    </form>
</div>

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

<div class="bg-white rounded-lg shadow-md overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-4 py-3 text-center w-12">
                    <input type="checkbox" id="select-all" class="rounded border-gray-300" onchange="toggleSelectAll(this)">
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">No. Rekening (Loan)</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kode Agunan</th>
                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Urutan</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Tipe Agunan</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Nilai Likuidasi</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Nilai Wajar / Taksasi</th>
                <th class="px-6 py-3 text-center text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse ($collaterals as $c)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-4 py-4 text-center">
                        <input type="checkbox" class="row-checkbox rounded border-gray-300" value="{{ $c->id }}" onchange="updateBulkActions()">
                    </td>
                    <td class="px-6 py-4 font-mono font-semibold text-blue-600">
                        {{ $c->loan_number }}
                    </td>
                    <td class="px-6 py-4 font-mono text-gray-700">{{ $c->collateral_code ?? '-' }}</td>
                    <td class="px-6 py-4 text-center">{{ $c->collateral_order }}</td>
                    <td class="px-6 py-4">
                        <span class="font-medium text-gray-900">{{ $c->collateral_type }}</span>
                        @if($c->collateralSetup)
                            <span class="text-xs text-gray-500 block">({{ $c->collateralSetup->collateral_ket }})</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-right font-medium text-gray-900">
                        Rp {{ number_format($c->liquidity_value, 2, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 text-right font-medium text-gray-900">
                        Rp {{ number_format($c->estimated_value, 2, ',', '.') }}
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $c->active_flag ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $c->active_flag ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        @if($c && $c->id)
                            <a href="{{ route('collateral.show', $c->id) }}" class="text-blue-600 hover:text-blue-800 font-medium">Detail</a>
                            <a href="{{ route('collateral.edit', $c->id) }}" class="text-green-600 hover:text-green-800 font-medium">Edit</a>
                            <form method="POST" action="{{ route('collateral.destroy', $c->id) }}" class="inline-block" id="delete-form-{{ $c->id }}">
                                @csrf @method('DELETE')
                            </form>
                            <button type="button" onclick="confirmDeleteCollateral({{ $c->id }}, '{{ $c->loan_number }}', '{{ $c->collateral_code }}')" class="text-red-600 hover:text-red-800 font-medium">
                                Hapus
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                        Belum ada data agunan / collateral.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $collaterals->links() }}
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
        title: `Hapus ${ids.length} Data Agunan?`,
        message: 'Semua data agunan/collateral yang dipilih akan dihapus secara permanen dari sistem.',
        detail: `Total: ${ids.length} item(s)`,
        type: 'danger',
        confirmText: 'Ya, Hapus Semua',
        cancelText: 'Batal',
        onConfirm: () => {
            const form = document.getElementById('bulk-delete-form');
            
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

function confirmDeleteCollateral(id, loanNumber, collateralCode) {
    window.showConfirmDialog({
        title: 'Hapus Data Agunan?',
        message: `Agunan untuk pembiayaan <strong>${loanNumber}</strong> akan dihapus secara permanen.`,
        detail: `Collateral Code: ${collateralCode || 'N/A'}`,
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
