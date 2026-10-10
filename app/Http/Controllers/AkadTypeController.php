<?php

namespace App\Http\Controllers;

use App\Models\AkadType;
use App\Services\SpoutImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AkadTypeController extends Controller
{
    public function index(): View
    {
        $akadTypes = AkadType::paginate(15);
        return view('akad-type.index', compact('akadTypes'));
    }

    public function create(): View
    {
        return view('akad-type.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kode_akad' => 'required|string|max:50|unique:akad_types',
            'nama_akad' => 'required|string|max:255',
            'kategori' => 'required|in:jual_beli,bagi_hasil,sewa,pinjaman',
            'ead_rule_type' => 'required|in:outstanding_balance,principal_arrears_conditional,zero_if_not_expired',
            'requires_due_date' => 'boolean',
            'active' => 'boolean',
        ]);

        AkadType::create($validated + [
            'requires_due_date' => $request->boolean('requires_due_date', false),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('akad-type.index')->with('success', 'Akad type created successfully');
    }

    public function show(AkadType $akadType): View
    {
        return view('akad-type.show', compact('akadType'));
    }

    public function edit(AkadType $akadType): View
    {
        return view('akad-type.edit', compact('akadType'));
    }

    public function update(Request $request, AkadType $akadType): RedirectResponse
    {
        $validated = $request->validate([
            'kode_akad' => 'required|string|max:50|unique:akad_types,kode_akad,' . $akadType->id,
            'nama_akad' => 'required|string|max:255',
            'kategori' => 'required|in:jual_beli,bagi_hasil,sewa,pinjaman',
            'ead_rule_type' => 'required|in:outstanding_balance,principal_arrears_conditional,zero_if_not_expired',
            'requires_due_date' => 'boolean',
            'active' => 'boolean',
        ]);

        $akadType->update($validated + [
            'requires_due_date' => $request->boolean('requires_due_date', $akadType->requires_due_date),
            'active' => $request->boolean('active', $akadType->active),
        ]);

        return redirect()->route('akad-type.index')->with('success', 'Akad type updated successfully');
    }

    public function destroy(AkadType $akadType): RedirectResponse
    {
        $akadType->delete();
        return redirect()->route('akad-type.index')->with('success', 'Akad type deleted successfully');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:akad_types,id']);
        
        $count = AkadType::whereIn('id', $validated['ids'])->delete();
        
        return redirect()->route('akad-type.index')->with('success', "{$count} akad types deleted successfully");
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'akad_types' => 'required|array|min:1',
            'akad_types.*.kode_akad' => 'required|string|max:50|distinct',
            'akad_types.*.nama_akad' => 'required|string|max:255',
            'akad_types.*.kategori' => 'required|in:jual_beli,bagi_hasil,sewa,pinjaman',
            'akad_types.*.ead_rule_type' => 'required|in:outstanding_balance,principal_arrears_conditional,zero_if_not_expired',
            'akad_types.*.requires_due_date' => 'boolean',
            'akad_types.*.active' => 'boolean',
        ]);

        $now = now();
        $upsertData = [];
        $existingCodes = AkadType::pluck('kode_akad')->toArray();

        foreach ($validated['akad_types'] as $item) {
            $upsertData[] = [
                'kode_akad' => $item['kode_akad'],
                'nama_akad' => $item['nama_akad'],
                'kategori' => $item['kategori'],
                'ead_rule_type' => $item['ead_rule_type'],
                'requires_due_date' => $item['requires_due_date'] ?? false,
                'active' => $item['active'] ?? true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $count = AkadType::upsert($upsertData, ['kode_akad'], ['nama_akad', 'kategori', 'ead_rule_type', 'requires_due_date', 'active', 'updated_at']);

        return redirect()->route('akad-type.index')->with('success', "{$count} akad types created or updated successfully");
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'updates' => 'required|array|min:1',
            'updates.*.id' => 'required|integer|exists:akad_types,id',
            'updates.*.nama_akad' => 'required|string|max:255',
            'updates.*.kategori' => 'required|in:jual_beli,bagi_hasil,sewa,pinjaman',
            'updates.*.ead_rule_type' => 'required|in:outstanding_balance,principal_arrears_conditional,zero_if_not_expired',
            'updates.*.requires_due_date' => 'boolean',
            'updates.*.active' => 'boolean',
        ]);

        $now = now();
        $upsertData = [];

        foreach ($validated['updates'] as $item) {
            $upsertData[] = [
                'id' => $item['id'],
                'nama_akad' => $item['nama_akad'],
                'kategori' => $item['kategori'],
                'ead_rule_type' => $item['ead_rule_type'],
                'requires_due_date' => $item['requires_due_date'] ?? false,
                'active' => $item['active'] ?? true,
                'updated_at' => $now,
            ];
        }

        $count = AkadType::upsert($upsertData, ['id'], ['nama_akad', 'kategori', 'ead_rule_type', 'requires_due_date', 'active', 'updated_at']);

        return redirect()->route('akad-type.index')->with('success', "{$count} akad types updated successfully");
    }

    public function importForm(): View
    {
        return view('akad-type.import');
    }

    public function uploadImport(Request $request): StreamedResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10000',
        ]);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $fullPath = $file->getRealPath();

        return new StreamedResponse(function () use ($fullPath, $extension) {
            $headerFields = ['kode_akad', 'nama_akad', 'kategori', 'ead_rule_type', 'requires_due_date', 'active'];

            $batchUpsert = function (array $rows) {
                $upsertData = [];
                $now = now();

                foreach ($rows as $row) {
                    if (empty(trim($row['kode_akad'] ?? ''))) {
                        continue;
                    }

                    $upsertData[] = [
                        'kode_akad' => trim($row['kode_akad']),
                        'nama_akad' => trim($row['nama_akad'] ?? ''),
                        'kategori' => trim($row['kategori'] ?? 'jual_beli'),
                        'ead_rule_type' => trim($row['ead_rule_type'] ?? 'outstanding_balance'),
                        'requires_due_date' => filter_var($row['requires_due_date'] ?? false, FILTER_VALIDATE_BOOLEAN),
                        'active' => filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($upsertData)) {
                    AkadType::upsert($upsertData, ['kode_akad'], ['nama_akad', 'kategori', 'ead_rule_type', 'requires_due_date', 'active', 'updated_at']);
                }
            };

            if (in_array($extension, ['csv', 'txt'], true)) {
                $result = SpoutImportService::importCsv($fullPath, $headerFields, $batchUpsert, null, 2000);
            } else {
                $service = new SpoutImportService($fullPath);
                $service->setBatchSize(500);

                $headerMap = [
                    'A' => 'kode_akad',
                    'B' => 'nama_akad',
                    'C' => 'kategori',
                    'D' => 'ead_rule_type',
                    'E' => 'requires_due_date',
                    'F' => 'active',
                ];

                $buffer = [];
                $result = $service->import(
                    $headerMap,
                    function ($rowData) use (&$buffer, $batchUpsert) {
                        $buffer[] = $rowData;
                        if (count($buffer) >= 500) {
                            $batchUpsert($buffer);
                            $buffer = [];
                        }
                    }
                );

                if (!empty($buffer)) {
                    $batchUpsert($buffer);
                }
            }

            @unlink($fullPath);

            echo "data: " . json_encode([
                'status' => 'complete',
                'imported' => $result['success'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
                'message' => "Import complete: {$result['success']} imported, {$result['failed']} failed",
            ]) . "\n\n";
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
