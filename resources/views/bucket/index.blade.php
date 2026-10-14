@extends('layouts.app')

@section('title', 'PD Netflow Bucket Config - CKPN Calculator')

@section('content')
<div class="mb-6 flex flex-wrap justify-between items-center gap-3">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">PD Netflow Bucket Config</h1>
        <p class="text-sm text-gray-500 mt-1">Kelola definisi bucket untuk klasifikasi Days Past Due (DPD) pada kalkulasi PD Netflow</p>
    </div>
    <a href="{{ route('bucket.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Tambah Bucket
    </a>
</div>

@if(session('success'))
    <div class="mb-6 bg-green-50 border-l-4 border-green-500 p-4 rounded-r-lg text-green-800 text-sm">
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-lg text-red-800 text-sm">
        {{ session('error') }}
    </div>
@endif

<form id="bulk-delete-form" method="POST" action="{{ route('bucket.bulk-destroy') }}" style="display:none;">
    @csrf @method('DELETE')
    <input type="hidden" name="ids" id="bulk-delete-ids">
</form>

<div id="bulk-actions-bar" class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg hidden">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-blue-900">
            <span id="selected-count">0</span> bucket dipilih
        </span>
        <div class="flex gap-2">
            <button type="button" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700 transition-colors" onclick="bulkDelete()">
                Nonaktifkan Terpilih
            </button>
            <button type="button" class="px-4 py-2 bg-gray-400 text-white rounded-lg text-sm font-medium hover:bg-gray-500 transition-colors" onclick="clearSelection()">
                Batal
            </button>
        </div>
    </div>
</div>

<!-- Info Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-lg shadow-md p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Bucket Aktif</p>
            <p class="text-xl font-bold text-gray-900">{{ $activeCount }}</p>
        </div>
    </div>
    <div class="bg-white rounded-lg shadow-md p-4 flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <p class="text-xs text-gray-500 uppercase tracking-wide">Bucket Nonaktif</p>
            <p class="text-xl font-bold text-gray-900">{{ $inactiveCount }}</p>
        </div>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-xs text-blue-800">
        <p class="font-semibold mb-1">Cara Kerja</p>
        <p>Urutan bucket menentukan alur transisi (1 = terbaik). <span class="font-medium">hari_min=0 & hari_max=0</span> berarti bucket Lancar; <span class="font-medium">hari_max &ge; 360</span> berarti bucket Write-off.</p>
    </div>
</div>

<!-- Tabs -->
<div class="mb-4 flex items-center gap-1 border-b border-gray-200">
    <button type="button" onclick="switchTab('active')" id="tab-btn-active"
        class="px-4 py-2.5 text-sm font-medium border-b-2 border-blue-600 text-blue-600 transition-colors">
        Aktif
        <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">{{ $activeCount }}</span>
    </button>
    <button type="button" onclick="switchTab('inactive')" id="tab-btn-inactive"
        class="px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 transition-colors">
        Nonaktif
        <span class="ml-1.5 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">{{ $inactiveCount }}</span>
    </button>
</div>

<!-- Active Table -->
<div id="tab-active" class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-center w-12">
                        <input type="checkbox" id="select-all" class="rounded border-gray-300" onchange="toggleSelectAll(this)">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-20">Urutan</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Deskripsi</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Rentang Hari</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($activeBuckets as $bucket)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-4 text-center">
                            <input type="checkbox" class="row-checkbox rounded border-gray-300" value="{{ $bucket->id }}" onchange="updateBulkActions()">
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-700 font-semibold text-xs">{{ $bucket->urutan }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-gray-100 text-gray-800 font-mono text-xs font-semibold">{{ $bucket->kode_bucket }}</span>
                        </td>
                        <td class="px-6 py-4 text-gray-900">{{ $bucket->deskripsi_hari }}</td>
                        <td class="px-6 py-4">
                            @if ($bucket->hari_min == 0 && $bucket->hari_max == 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Lancar (0 hari)</span>
                            @elseif ($bucket->hari_max === null)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">&ge; {{ $bucket->hari_min }} hari (tanpa batas)</span>
                            @elseif ($bucket->hari_max >= 360)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">&gt; {{ $bucket->hari_min }} hari / Write-off</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">{{ $bucket->hari_min }} - {{ $bucket->hari_max }} hari</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 space-x-1 text-right whitespace-nowrap">
                            <a href="{{ route('bucket.edit', $bucket) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-green-600 hover:bg-green-50 rounded transition-colors font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>
                            <form method="POST" action="{{ route('bucket.destroy', $bucket) }}" class="inline-block" id="deactivate-form-{{ $bucket->id }}">
                                @csrf @method('DELETE')
                            </form>
                            <button type="button" onclick="confirmDeactivate({{ $bucket->id }}, '{{ $bucket->kode_bucket }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-red-600 hover:bg-red-50 rounded transition-colors font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Nonaktifkan
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                            <p class="text-gray-500 font-medium">Belum ada bucket aktif</p>
                            <a href="{{ route('bucket.create') }}" class="text-blue-600 hover:underline text-sm">Tambah bucket baru</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Inactive Table -->
<form id="bulk-restore-form" method="POST" action="{{ route('bucket.bulk-restore') }}" style="display:none;">
    @csrf
    <input type="hidden" name="ids" id="bulk-restore-ids">
</form>

<div id="bulk-restore-bar" class="mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-lg hidden">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-emerald-900">
            <span id="restore-selected-count">0</span> bucket dipilih
        </span>
        <div class="flex gap-2">
            <button type="button" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm font-medium hover:bg-emerald-700 transition-colors" onclick="bulkRestore()">
                Aktifkan Terpilih
            </button>
            <button type="button" class="px-4 py-2 bg-gray-400 text-white rounded-lg text-sm font-medium hover:bg-gray-500 transition-colors" onclick="clearRestoreSelection()">
                Batal
            </button>
        </div>
    </div>
</div>

<div id="tab-inactive" class="bg-white rounded-lg shadow-md overflow-hidden hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3 text-center w-12">
                        <input type="checkbox" id="select-all-inactive" class="rounded border-gray-300" onchange="toggleSelectAllInactive(this)">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider w-20">Urutan</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Kode</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Deskripsi</th>
                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Rentang Hari</th>
                    <th class="px-6 py-3 text-right text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse ($inactiveBuckets as $bucket)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-4 text-center">
                            <input type="checkbox" class="row-checkbox-inactive rounded border-gray-300" value="{{ $bucket->id }}" onchange="updateBulkRestore()">
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-full bg-gray-100 text-gray-400 font-semibold text-xs">{{ $bucket->urutan }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md bg-gray-100 text-gray-500 font-mono text-xs font-semibold">{{ $bucket->kode_bucket }}</span>
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ $bucket->deskripsi_hari }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">
                                {{ $bucket->hari_min == 0 && $bucket->hari_max == 0 ? 'Lancar (0 hari)' : ($bucket->hari_max === null ? '≥ '.$bucket->hari_min.' hari (tanpa batas)' : ($bucket->hari_max >= 360 ? '> '.$bucket->hari_min.' hari / WO' : $bucket->hari_min.' - '.$bucket->hari_max.' hari')) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('bucket.restore', $bucket) }}" class="inline-block" id="restore-form-{{ $bucket->id }}">
                                @csrf
                            </form>
                            <button type="button" onclick="confirmRestore({{ $bucket->id }}, '{{ $bucket->kode_bucket }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-emerald-600 hover:bg-emerald-50 rounded transition-colors font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Aktifkan
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <p class="text-gray-500 font-medium">Tidak ada bucket nonaktif</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<script>
function switchTab(tab) {
    const isActive = tab === 'active';
    document.getElementById('tab-active').classList.toggle('hidden', !isActive);
    document.getElementById('tab-inactive').classList.toggle('hidden', isActive);
    document.getElementById('tab-btn-active').classList.toggle('border-blue-600', isActive);
    document.getElementById('tab-btn-active').classList.toggle('text-blue-600', isActive);
    document.getElementById('tab-btn-active').classList.toggle('border-transparent', !isActive);
    document.getElementById('tab-btn-active').classList.toggle('text-gray-500', !isActive);
    document.getElementById('tab-btn-inactive').classList.toggle('border-blue-600', !isActive);
    document.getElementById('tab-btn-inactive').classList.toggle('text-blue-600', !isActive);
    document.getElementById('tab-btn-inactive').classList.toggle('border-transparent', isActive);
    document.getElementById('tab-btn-inactive').classList.toggle('text-gray-500', isActive);
    if (isActive) document.getElementById('bulk-restore-bar').classList.add('hidden');
}

function confirmDeactivate(id, kode) {
    window.showConfirmDialog({
        title: 'Nonaktifkan Bucket?',
        message: `Bucket <strong>${kode}</strong> akan dinonaktifkan dan tidak digunakan dalam kalkulasi PD Netflow.`,
        detail: 'Data historis yang sudah dikalkulasi tidak akan berubah.',
        type: 'danger',
        confirmText: 'Ya, Nonaktifkan',
        cancelText: 'Batal',
        onConfirm: () => document.getElementById(`deactivate-form-${id}`).submit()
    });
}

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
    if (ids.length === 0) return alert('Pilih minimal satu bucket');

    window.showConfirmDialog({
        title: `Nonaktifkan ${ids.length} Bucket?`,
        message: 'Semua bucket yang dipilih akan dinonaktifkan dan tidak digunakan dalam kalkulasi PD Netflow.',
        detail: `Total: ${ids.length} item(s)`,
        type: 'danger',
        confirmText: 'Ya, Nonaktifkan Semua',
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

function confirmRestore(id, kode) {
    window.showConfirmDialog({
        title: 'Aktifkan Bucket?',
        message: `Bucket <strong>${kode}</strong> akan diaktifkan kembali dan digunakan dalam kalkulasi PD Netflow.`,
        detail: 'Pastikan rentang hari tidak tumpang tindih dengan bucket aktif lainnya.',
        type: 'info',
        confirmText: 'Ya, Aktifkan',
        cancelText: 'Batal',
        onConfirm: () => document.getElementById(`restore-form-${id}`).submit()
    });
}

function toggleSelectAllInactive(checkbox) {
    document.querySelectorAll('.row-checkbox-inactive').forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkRestore();
}

function updateBulkRestore() {
    const checked = document.querySelectorAll('.row-checkbox-inactive:checked').length;
    const bar = document.getElementById('bulk-restore-bar');
    const count = document.getElementById('restore-selected-count');

    count.textContent = checked;
    if (checked > 0) {
        bar.classList.remove('hidden');
    } else {
        bar.classList.add('hidden');
        document.getElementById('select-all-inactive').checked = false;
    }
}

function bulkRestore() {
    const ids = Array.from(document.querySelectorAll('.row-checkbox-inactive:checked')).map(cb => cb.value);
    if (ids.length === 0) return alert('Pilih minimal satu bucket');

    window.showConfirmDialog({
        title: `Aktifkan ${ids.length} Bucket?`,
        message: 'Semua bucket yang dipilih akan diaktifkan kembali dan digunakan dalam kalkulasi PD Netflow.',
        detail: `Total: ${ids.length} item(s)`,
        type: 'info',
        confirmText: 'Ya, Aktifkan Semua',
        cancelText: 'Batal',
        onConfirm: () => {
            const form = document.getElementById('bulk-restore-form');
            const input = document.getElementById('bulk-restore-ids');

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

function clearRestoreSelection() {
    document.querySelectorAll('.row-checkbox-inactive').forEach(cb => cb.checked = false);
    document.getElementById('select-all-inactive').checked = false;
    updateBulkRestore();
}
</script>
@endsection
