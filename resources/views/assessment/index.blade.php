@extends('layouts.app')

@section('title', 'Assessment Periods - CKPN Calculator')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <h1 class="text-2xl font-bold text-gray-900">Assessment Periods</h1>
    <a href="{{ route('assessment.create') }}" class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        New Assessment
    </a>
</div>

<!-- Bulk Actions Bar -->
<div id="bulk-actions-bar" class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg hidden">
    <div class="flex justify-between items-center">
        <span class="text-sm font-medium text-gray-700">
            <span id="selected-count">0</span> item(s) selected
        </span>
        <button onclick="bulkDelete()" class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Hapus Terpilih
        </button>
    </div>
</div>

<div class="bg-white rounded-lg shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
        <thead class="bg-gray-50 border-b border-gray-200">
            <tr>
                <th class="px-6 py-3 text-left">
                    <input type="checkbox" id="select-all" class="w-4 h-4 rounded border-gray-300" onchange="toggleSelectAll(this)">
                </th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Period Name</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Month</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Year</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Assessment Date</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-700 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse ($assessments as $assessment)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4">
                        <input type="checkbox" class="row-checkbox w-4 h-4 rounded border-gray-300" value="{{ $assessment->id }}" onchange="updateBulkActionsBar()">
                    </td>
                    <td class="px-6 py-4 font-semibold text-gray-900">{{ $assessment->period_name }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $assessment->fiscal_month }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $assessment->fiscal_year }}</td>
                    <td class="px-6 py-4 text-gray-900 font-mono text-xs">{{ $assessment->assessment_date->format('d M Y') }}</td>
                    <td class="px-6 py-4">
                        @php
                            $statusColors = [
                                'DRAFT' => 'bg-gray-100 text-gray-800',
                                'VALIDATING' => 'bg-yellow-100 text-yellow-800',
                                'CALCULATING' => 'bg-blue-100 text-blue-800',
                                'REVIEW' => 'bg-purple-100 text-purple-800',
                                'APPROVED' => 'bg-green-100 text-green-800',
                                'LOCKED' => 'bg-red-100 text-red-800',
                            ];
                            $color = $statusColors[$assessment->status] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $color }}">
                            {{ $assessment->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 space-x-1 text-sm">
                        @if($assessment && $assessment->id)
                            <a href="{{ route('assessment.show', $assessment->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-blue-600 hover:bg-blue-50 rounded transition-colors font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                View
                            </a>
                            <a href="{{ route('assessment.edit', $assessment->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 text-green-600 hover:bg-green-50 rounded transition-colors font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Edit
                            </a>
                            <button type="button" onclick="confirmDeleteAssessment({{ $assessment->id }}, '{{ $assessment->period_name }}')" class="inline-flex items-center gap-1 px-3 py-1.5 text-red-600 hover:bg-red-50 rounded transition-colors font-medium">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Delete
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center">
                        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        <p class="text-gray-500 font-medium">No assessments found</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="mt-6">
    {{ $assessments->links() }}
</div>

<!-- Hidden Bulk Delete Form -->
<form id="bulk-delete-form" method="POST" action="{{ route('assessment.bulk-destroy') }}" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<script>
function updateBulkActionsBar() {
    const checked = document.querySelectorAll('.row-checkbox:checked').length;
    const bar = document.getElementById('bulk-actions-bar');
    document.getElementById('selected-count').textContent = checked;
    bar.classList.toggle('hidden', checked === 0);
}

function toggleSelectAll(checkbox) {
    document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = checkbox.checked);
    updateBulkActionsBar();
}

function confirmDeleteAssessment(id, periodName) {
    window.showConfirmDialog({
        title: 'Hapus Assessment Period?',
        message: `Assessment period "${periodName}" akan dihapus secara permanen.`,
        detail: `ID: ${id}`,
        type: 'danger',
        confirmText: 'Ya, Hapus',
        cancelText: 'Batal',
        onConfirm: () => {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `{{ route('assessment.destroy', ':id') }}`.replace(':id', id);
            form.innerHTML = '@csrf @method("DELETE")';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function bulkDelete() {
    const ids = Array.from(document.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
    if (ids.length === 0) return alert('Pilih minimal satu data');
    
    window.showConfirmDialog({
        title: `Hapus ${ids.length} Assessment Period?`,
        message: 'Semua assessment period yang dipilih akan dihapus secara permanen dari sistem.',
        detail: `Total: ${ids.length} item(s)`,
        type: 'danger',
        confirmText: 'Ya, Hapus Semua',
        cancelText: 'Batal',
        onConfirm: () => {
            const form = document.getElementById('bulk-delete-form');
            
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
</script>
@endsection
