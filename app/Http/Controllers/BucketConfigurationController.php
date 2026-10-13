<?php

namespace App\Http\Controllers;

use App\Models\BucketDefinition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BucketConfigurationController extends Controller
{
    public function index()
    {
        $buckets = BucketDefinition::where('active', true)
            ->orderBy('id', 'asc')
            ->get();

        return view('bucket.index', compact('buckets'));
    }

    public function create()
    {
        return view('bucket.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'kode_bucket' => 'required|string|unique:bucket_definitions,kode_bucket',
            'deskripsi_hari' => 'required|string',
            'hari_min' => 'required|integer|min:0',
            'hari_max' => 'required|integer|min:0|gte:hari_min',
            'urutan' => 'required|integer|min:1',
        ]);

        BucketDefinition::create($validated);

        return redirect()->route('bucket.index')->with('success', 'Bucket berhasil ditambahkan');
    }

    public function edit(BucketDefinition $bucket)
    {
        return view('bucket.edit', compact('bucket'));
    }

    public function update(Request $request, BucketDefinition $bucket)
    {
        $validated = $request->validate([
            'kode_bucket' => 'required|string|unique:bucket_definitions,kode_bucket,' . $bucket->id,
            'deskripsi_hari' => 'required|string',
            'hari_min' => 'required|integer|min:0',
            'hari_max' => 'required|integer|min:0|gte:hari_min',
            'urutan' => 'required|integer|min:1',
        ]);

        $bucket->update($validated);

        return redirect()->route('bucket.index')->with('success', 'Bucket berhasil diperbarui');
    }

    public function destroy(BucketDefinition $bucket)
    {
        $bucket->update(['active' => false]);

        return redirect()->route('bucket.index')->with('success', 'Bucket berhasil dihapus');
    }

    public function restore(BucketDefinition $bucket)
    {
        $bucket->update(['active' => true]);

        return redirect()->route('bucket.index')->with('success', 'Bucket berhasil dipulihkan');
    }
}
