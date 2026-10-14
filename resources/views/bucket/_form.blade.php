@php
    $isEdit = isset($bucket) && $bucket !== null;
    $oldKode = old('kode_bucket', $bucket->kode_bucket ?? '');
    $oldDeskripsi = old('deskripsi_hari', $bucket->deskripsi_hari ?? '');
    $oldHariMin = old('hari_min', $bucket->hari_min ?? 0);
    $oldHariMax = old('hari_max', $bucket->hari_max ?? null);
    $oldUrutan = old('urutan', $bucket->urutan ?? $nextUrutan ?? 1);
@endphp

<div class="grid grid-cols-1 md:grid-cols-2 gap-5">
    <!-- Kode Bucket -->
    <div>
        <label for="kode_bucket" class="block text-sm font-medium text-gray-700 mb-1.5">
            Kode Bucket <span class="text-red-500">*</span>
        </label>
        <input type="text" id="kode_bucket" name="kode_bucket" value="{{ $oldKode }}" required maxlength="20"
            placeholder="Contoh: B1, B2, WO"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition-colors text-sm">
        <p class="mt-1.5 text-xs text-gray-500">Kode unik untuk bucket (maks. 20 karakter)</p>
        @error('kode_bucket') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <!-- Urutan -->
    <div>
        <label for="urutan" class="block text-sm font-medium text-gray-700 mb-1.5">
            Urutan <span class="text-red-500">*</span>
        </label>
        <input type="number" id="urutan" name="urutan" value="{{ $oldUrutan }}" required min="1"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition-colors text-sm">
        <p class="mt-1.5 text-xs text-gray-500">Urutan transisi (1 = bucket terbaik)</p>
        @error('urutan') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <!-- Deskripsi -->
    <div class="md:col-span-2">
        <label for="deskripsi_hari" class="block text-sm font-medium text-gray-700 mb-1.5">
            Deskripsi Hari <span class="text-red-500">*</span>
        </label>
        <input type="text" id="deskripsi_hari" name="deskripsi_hari" value="{{ $oldDeskripsi }}" required maxlength="100"
            placeholder="Contoh: 0 hari (Lancar), 1 - 30 hari"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition-colors text-sm">
        <p class="mt-1.5 text-xs text-gray-500">Label yang akan tampil di laporan</p>
        @error('deskripsi_hari') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <!-- Hari Min -->
    <div>
        <label for="hari_min" class="block text-sm font-medium text-gray-700 mb-1.5">
            Hari Minimum (DPD Min) <span class="text-red-500">*</span>
        </label>
        <input type="number" id="hari_min" name="hari_min" value="{{ $oldHariMin }}" required min="0"
            class="w-full px-3.5 py-2.5 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition-colors text-sm">
        <p class="mt-1.5 text-xs text-gray-500">Batas bawah hari tunggakan (inklusif)</p>
        @error('hari_min') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>

    <!-- Hari Max -->
    <div>
        <label for="hari_max" class="block text-sm font-medium text-gray-700 mb-1.5">
            Hari Maksimum (DPD Max)
        </label>
        <div class="flex items-center gap-3">
            <input type="number" id="hari_max" name="hari_max" value="{{ $oldHariMax }}" min="0"
                placeholder="Kosongkan jika tanpa batas"
                class="flex-1 w-full px-3.5 py-2.5 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 focus:outline-none transition-colors text-sm">
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 whitespace-nowrap cursor-pointer">
                <input type="checkbox" id="no_max" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500/20" {{ $oldHariMax === null ? 'checked' : '' }}>
                Tanpa batas
            </label>
        </div>
        <p class="mt-1.5 text-xs text-gray-500">Centang "Tanpa batas" untuk bucket terakhir (misal: <strong>&ge; 360 hari</strong>) — tidak ada batas atas hari tunggakan</p>
        @error('hari_max') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<!-- Info Box -->
<div class="mt-6 bg-amber-50 border border-amber-200 rounded-lg p-4">
    <div class="flex gap-3">
        <svg class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <div class="text-xs text-amber-800">
            <p class="font-semibold mb-1">Catatan Konfigurasi</p>
            <ul class="list-disc list-inside space-y-0.5">
                <li><strong>Lancar:</strong> isi <code class="bg-amber-100 px-1 rounded">hari_min = 0</code> dan <code class="bg-amber-100 px-1 rounded">hari_max = 0</code></li>
                <li><strong>Bucket terakhir / WO:</strong> centang <code class="bg-amber-100 px-1 rounded">Tanpa batas</code> (misal: <code class="bg-amber-100 px-1 rounded">&ge; 360 hari</code>)</li>
                <li>Rentang hari antar bucket harus berurutan dan tidak tumpang tindih</li>
                <li>Hanya satu bucket yang boleh "Tanpa batas" (biasanya urutan terakhir)</li>
            </ul>
        </div>
    </div>
</div>

<script>
    const hariMaxInput = document.getElementById('hari_max');
    const noMaxCheckbox = document.getElementById('no_max');

    function toggleHariMax() {
        if (noMaxCheckbox.checked) {
            hariMaxInput.value = '';
            hariMaxInput.disabled = true;
            hariMaxInput.classList.add('bg-gray-100', 'text-gray-400');
        } else {
            hariMaxInput.disabled = false;
            hariMaxInput.classList.remove('bg-gray-100', 'text-gray-400');
        }
    }

    noMaxCheckbox.addEventListener('change', toggleHariMax);
    toggleHariMax();
</script>

<!-- Buttons -->
<div class="mt-6 flex items-center gap-3">
    <button type="submit" class="inline-flex items-center gap-2 bg-blue-600 text-white px-5 py-2.5 rounded-lg font-medium hover:bg-blue-700 transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ $isEdit ? 'Simpan Perubahan' : 'Simpan Bucket' }}
    </button>
    <a href="{{ route('bucket.index') }}" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors text-sm">
        Batal
    </a>
</div>
