<?php

namespace App\Http\Controllers;

use App\Models\BucketDefinition;
use Illuminate\Http\Request;

class BucketConfigurationController extends Controller
{
    public function index()
    {
        $activeBuckets = BucketDefinition::where('active', true)->orderBy('urutan', 'asc')->get();
        $inactiveBuckets = BucketDefinition::where('active', false)->orderBy('urutan', 'asc')->get();

        return view('bucket.index', [
            'activeBuckets' => $activeBuckets,
            'inactiveBuckets' => $inactiveBuckets,
            'activeCount' => $activeBuckets->count(),
            'inactiveCount' => $inactiveBuckets->count(),
        ]);
    }

    public function create()
    {
        $nextUrutan = (BucketDefinition::max('urutan') ?? 0) + 1;
        $bucket = null;

        return view('bucket.create', compact('bucket', 'nextUrutan'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateBucket($request);

        BucketDefinition::create($validated + ['active' => true]);

        return redirect()->route('bucket.index')->with('success', 'Bucket "' . $validated['kode_bucket'] . '" berhasil ditambahkan');
    }

    public function edit(BucketDefinition $bucket)
    {
        return view('bucket.edit', ['bucket' => $bucket, 'nextUrutan' => null]);
    }

    public function update(Request $request, BucketDefinition $bucket)
    {
        $validated = $this->validateBucket($request, $bucket->id);

        $bucket->update($validated);

        return redirect()->route('bucket.index')->with('success', 'Bucket "' . $validated['kode_bucket'] . '" berhasil diperbarui');
    }

    public function destroy(BucketDefinition $bucket)
    {
        $bucket->update(['active' => false]);

        return redirect()->route('bucket.index')->with('success', 'Bucket "' . $bucket->kode_bucket . '" berhasil dinonaktifkan');
    }

    public function restore(BucketDefinition $bucket)
    {
        $bucket->update(['active' => true]);

        return redirect()->route('bucket.index')->with('success', 'Bucket "' . $bucket->kode_bucket . '" berhasil diaktifkan kembali');
    }

    public function bulkDestroy(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:bucket_definitions,id',
        ]);

        $count = BucketDefinition::whereIn('id', $validated['ids'])->update(['active' => false]);

        return redirect()->route('bucket.index')->with('success', "{$count} bucket berhasil dinonaktifkan");
    }

    private function validateBucket(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'kode_bucket' => 'required|string|max:20|unique:bucket_definitions,kode_bucket' . ($ignoreId ? ',' . $ignoreId : ''),
            'deskripsi_hari' => 'required|string|max:100',
            'hari_min' => 'required|integer|min:0',
            'hari_max' => 'required|integer|min:0|gte:hari_min',
            'urutan' => 'required|integer|min:1',
        ], [
            'kode_bucket.unique' => 'Kode bucket sudah digunakan',
            'hari_max.gte' => 'Hari maksimum harus lebih besar atau sama dengan hari minimum',
        ]);
    }
}
