@extends('layouts.app')

@section('title', 'Users - CKPN Calculator')

@section('page-title', 'User Management')

@section('content')
<div class="w-full">
    <!-- Header -->
    <div class="flex justify-between items-center mb-8">
        <h2 class="text-2xl font-bold text-gray-800">Users</h2>
        <a href="{{ route('user.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add User
        </a>
    </div>

    <!-- Bulk Actions Bar -->
    <div id="bulk-actions-bar" class="mb-4 bg-red-50 border border-red-200 rounded-lg p-4 hidden">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-red-900">
                <span id="selected-count">0</span> user dipilih
            </span>
            <button type="button" onclick="bulkDelete()" class="inline-flex items-center gap-2 bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Hapus Terpilih
            </button>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
            <thead class="bg-gray-100 border-b">
                <tr>
                    <th class="px-6 py-3 text-center font-semibold text-gray-700 w-12">
                        <input type="checkbox" id="select-all" onchange="toggleSelectAll(this)">
                    </th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Name</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Email</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Role</th>
                    <th class="px-6 py-3 text-left font-semibold text-gray-700">Office</th>
                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Status</th>
                    <th class="px-6 py-3 text-center font-semibold text-gray-700">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($users as $user)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-center">
                            @if(auth()->id() !== $user->id)
                                <input type="checkbox" class="row-checkbox" value="{{ $user->id }}" onchange="updateBulkActionsBar()">
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-900 font-medium">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-gray-600">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $user->role?->nama_role ?? 'N/A' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-gray-600">{{ $user->office?->office_name ?? '-' }}</td>
                        <td class="px-6 py-4 text-center">
                            @if ($user->active)
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">Active</span>
                            @else
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Inactive</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center flex gap-2 justify-center">
                            <a href="{{ route('user.show', $user) }}" class="text-blue-600 hover:text-blue-800 transition-colors" title="View">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </a>
                            <a href="{{ route('user.edit', $user) }}" class="text-amber-600 hover:text-amber-800 transition-colors" title="Edit">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </a>
                            @if (auth()->id() !== $user->id)
                                <button type="button" onclick="confirmDeleteUser({{ $user->id }}, '{{ $user->name }}')" class="text-red-600 hover:text-red-800 transition-colors" title="Delete">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>

    <!-- Pagination -->
    <div class="mt-6">
        {{ $users->links() }}
    </div>
</div>

<!-- Bulk Delete Form -->
<form method="POST" action="{{ route('user.bulk-destroy') }}" id="bulk-delete-form" style="display:none;">
    @csrf @method('DELETE')
</form>

<!-- Single Delete Forms -->
@foreach ($users as $user)
    @if (auth()->id() !== $user->id)
        <form action="{{ route('user.destroy', $user) }}" method="POST" id="delete-form-{{ $user->id }}" style="display:none;">
            @csrf @method('DELETE')
        </form>
    @endif
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
    const ids = Array.from(checkboxes).map(cb => parseInt(cb.value, 10));
    
    if (ids.length === 0) return;
    
    window.showConfirmDialog({
        title: 'Hapus User Terpilih?',
        message: `<strong>${ids.length} user</strong> akan dihapus secara permanen dari sistem.`,
        detail: `${ids.length} user dipilih`,
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

<script>
function confirmDeleteUser(id, userName) {
    window.showConfirmDialog({
        title: 'Hapus User?',
        message: `User <strong>${userName}</strong> akan dihapus secara permanen dari sistem.`,
        detail: `User: ${userName}`,
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
