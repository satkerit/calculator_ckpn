@extends('layouts.app')

@section('title', 'View User - CKPN Calculator')

@section('page-title', 'User Details')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-lg shadow-lg p-8">
        <h2 class="text-2xl font-bold mb-8">{{ $user->name }}</h2>

        <div class="grid grid-cols-1 gap-6">
            <!-- Name -->
            <div class="p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                <p class="text-xs uppercase font-semibold text-gray-600 mb-1">Name</p>
                <p class="text-lg font-semibold text-gray-900">{{ $user->name }}</p>
            </div>

            <!-- Email -->
            <div class="p-4 bg-blue-50 rounded-lg border-l-4 border-blue-500">
                <p class="text-xs uppercase font-semibold text-gray-600 mb-1">Email</p>
                <p class="text-lg font-semibold text-gray-900">{{ $user->email }}</p>
            </div>

            <!-- Role -->
            <div class="p-4 bg-purple-50 rounded-lg border-l-4 border-purple-500">
                <p class="text-xs uppercase font-semibold text-gray-600 mb-1">Role</p>
                <div class="flex items-center gap-2 mt-1">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-700">
                        {{ $user->role?->nama_role ?? 'N/A' }}
                    </span>
                </div>
            </div>

            <!-- Office -->
            <div class="p-4 bg-green-50 rounded-lg border-l-4 border-green-500">
                <p class="text-xs uppercase font-semibold text-gray-600 mb-1">Office</p>
                <p class="text-lg font-semibold text-gray-900">{{ $user->office?->office_name ?? '-' }}</p>
            </div>

            <!-- Status -->
            <div class="p-4 bg-orange-50 rounded-lg border-l-4 border-orange-500">
                <p class="text-xs uppercase font-semibold text-gray-600 mb-1">Status</p>
                <div class="flex items-center gap-2 mt-1">
                    @if ($user->active)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
                            ✓ Active
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-700">
                            ✕ Inactive
                        </span>
                    @endif
                </div>
            </div>

            <!-- Created -->
            <div class="p-4 bg-gray-50 rounded-lg border-l-4 border-gray-400">
                <p class="text-xs uppercase font-semibold text-gray-600 mb-1">Created</p>
                <p class="text-sm text-gray-600">{{ $user->created_at?->format('d M Y, H:i') ?? '-' }}</p>
            </div>

            <!-- Updated -->
            <div class="p-4 bg-gray-50 rounded-lg border-l-4 border-gray-400">
                <p class="text-xs uppercase font-semibold text-gray-600 mb-1">Last Updated</p>
                <p class="text-sm text-gray-600">{{ $user->updated_at?->format('d M Y, H:i') ?? '-' }}</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex gap-3 mt-8 pt-6 border-t">
            <a href="{{ route('user.edit', $user) }}" class="flex-1 bg-blue-600 text-white px-4 py-3 rounded-lg font-semibold hover:bg-blue-700 transition-colors text-center flex items-center justify-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit User
            </a>
            @if (auth()->id() !== $user->id)
                <form action="{{ route('user.destroy', $user) }}" method="POST" class="flex-1" id="delete-form-{{ $user->id }}">
                    @csrf @method('DELETE')
                </form>
                <button type="button" onclick="confirmDeleteUser({{ $user->id }}, '{{ $user->name }}')" class="flex-1 bg-red-600 text-white px-4 py-3 rounded-lg font-semibold hover:bg-red-700 transition-colors flex items-center justify-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete User
                </button>
            @endif
            <a href="{{ route('user.index') }}" class="flex-1 bg-gray-500 text-white px-4 py-3 rounded-lg font-semibold hover:bg-gray-600 transition-colors text-center">Back</a>
        </div>
    </div>
</div>

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
