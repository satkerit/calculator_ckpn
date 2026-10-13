@extends('layouts.app')

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center my-4">
        <h1 class="h3 mb-0 text-gray-800">Edit Bucket PD Netflow</h1>
        <a href="{{ route('bucket.index') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Kembali
        </a>
    </div>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Edit Bucket: {{ $bucket->kode_bucket }}</h6>
        </div>
        <div class="card-body">
            <form action="{{ route('bucket.update', $bucket) }}" method="POST">
                @csrf
                @method('PUT')
                @include('bucket._form')
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Update
                    </button>
                    <a href="{{ route('bucket.index') }}" class="btn btn-secondary ms-2">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
