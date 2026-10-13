@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <h1 class="h3 mb-0 text-gray-800">Setup Bucket PD Netflow</h1>
        <a href="{{ route('bucket.create') }}" class="btn btn-primary">
            <i class="fas fa-plus"></i> Tambah Bucket
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Daftar Bucket & Rentang Hari (DPD)</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-striped" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Urutan</th>
                            <th>Kode Bucket</th>
                            <th>Deskripsi</th>
                            <th>Hari Min</th>
                            <th>Hari Max</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($buckets as $bucket)
                            <tr>
                                <td>{{ $bucket->urutan }}</td>
                                <td><span class="badge bg-secondary">{{ $bucket->kode_bucket }}</span></td>
                                <td>{{ $bucket->deskripsi_hari }}</td>
                                <td>{{ $bucket->hari_min }} hari</td>
                                <td>{{ $bucket->hari_max >= 360 ? '> ' . $bucket->hari_min : $bucket->hari_max . ' hari' }}</td>
                                <td>
                                    <a href="{{ route('bucket.edit', $bucket) }}" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form action="{{ route('bucket.destroy', $bucket) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menonaktifkan bucket ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">Belum ada bucket yang diset.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
