@extends('layouts.app')

@section('title', 'Edit PD Bucket - CKPN Calculator')

@section('content')
<div class="mb-6">
    <nav class="flex items-center gap-2 text-sm text-gray-500 mb-3">
        <a href="{{ route('bucket.index') }}" class="hover:text-blue-600 transition-colors">PD Bucket Config</a>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-900 font-medium">Edit {{ $bucket->kode_bucket }}</span>
    </nav>
    <h1 class="text-2xl font-bold text-gray-900">Edit Bucket</h1>
    <p class="text-sm text-gray-500 mt-1">Ubah definisi bucket <span class="font-mono font-semibold text-gray-700">{{ $bucket->kode_bucket }}</span></p>
</div>

<div class="bg-white rounded-lg shadow-md p-6 max-w-3xl">
    <form action="{{ route('bucket.update', $bucket) }}" method="POST">
        @csrf
        @method('PUT')
        @include('bucket._form')
    </form>
</div>
@endsection
