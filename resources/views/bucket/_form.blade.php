<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label fw-semibold">Kode Bucket <span class="text-danger">*</span></label>
        <input type="text" name="kode_bucket" class="form-control @error('kode_bucket') is-invalid @enderror"
            value="{{ old('kode_bucket', $bucket->kode_bucket ?? '') }}"
            placeholder="Contoh: B1, B2, WO">
        @error('kode_bucket')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <small class="text-muted">Kode unik untuk identifikasi bucket (maksimal 10 karakter)</small>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Urutan <span class="text-danger">*</span></label>
        <input type="number" name="urutan" class="form-control @error('urutan') is-invalid @enderror"
            value="{{ old('urutan', $bucket->urutan ?? '') }}"
            min="1" placeholder="Contoh: 1, 2, 3">
        @error('urutan')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <small class="text-muted">Urutan bucket dari terkecil (lancar) ke terbesar (loss)</small>
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Deskripsi <span class="text-danger">*</span></label>
        <input type="text" name="deskripsi_hari" class="form-control @error('deskripsi_hari') is-invalid @enderror"
            value="{{ old('deskripsi_hari', $bucket->deskripsi_hari ?? '') }}"
            placeholder="Contoh: 0 hari (Lancar), 1-30 hari, > 360 hari (Write-off)">
        @error('deskripsi_hari')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Hari Min <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" name="hari_min" class="form-control @error('hari_min') is-invalid @enderror"
                value="{{ old('hari_min', $bucket->hari_min ?? 0) }}"
                min="0" placeholder="0">
            <span class="input-group-text">hari</span>
        </div>
        @error('hari_min')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <small class="text-muted">Batas bawah DPD (Days Past Due). 0 untuk bucket lancar.</small>
    </div>

    <div class="col-md-6">
        <label class="form-label fw-semibold">Hari Max <span class="text-danger">*</span></label>
        <div class="input-group">
            <input type="number" name="hari_max" class="form-control @error('hari_max') is-invalid @enderror"
                value="{{ old('hari_max', $bucket->hari_max ?? 0) }}"
                min="0" placeholder="0">
            <span class="input-group-text">hari</span>
        </div>
        @error('hari_max')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
        <small class="text-muted">Batas atas DPD. Untuk bucket Write-off, set ke 999 atau lebih besar dari range terakhir.</small>
    </div>
</div>

<div class="alert alert-info mt-4">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Catatan:</strong> Jumlah bucket dan rentang hari menentukan struktur matriks transisi PD Netflow.
    Setiap perubahan bucket akan berpengaruh pada semua kalkulasi PD Netflow berikutnya.
    Pastikan <strong>tidak ada overlap</strong> antar rentang hari antar bucket.
</div>
