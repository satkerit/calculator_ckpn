<?php

namespace App\Http\Controllers;

use App\Models\ProductAkadMapping;
use App\Models\AkadType;
use App\Services\ProductAkadMappingService;
use App\Services\SpoutImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductAkadMappingController extends Controller
{
    public function index()
    {
        $mappings = ProductAkadMapping::with('akadType')->orderBy('product_code')->paginate(50);
        return view('product-akad.index', compact('mappings'));
    }

    public function create()
    {
        $akadTypes = \App\Models\AkadType::where('active', true)->get();
        return view('product-akad.create', compact('akadTypes'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_code' => 'required|string|max:50|unique:product_akad_mappings,product_code',
            'product_name' => 'required|string|max:255',
            'akad_code' => 'required|string|exists:akad_types,kode_akad',
            'ead_rule_type' => 'required|in:MURABAHAH,MULTIJASA,MUSYARAKAH,IMBT',
            'active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $akad = \App\Models\AkadType::where('kode_akad', $data['akad_code'])->first();
        $data['akad_name'] = $akad->nama_akad;

        ProductAkadMapping::create($data);
        ProductAkadMappingService::invalidateCache();

        return redirect()->route('product-akad.index')->with('success', 'Mapping berhasil dibuat');
    }

    public function edit(ProductAkadMapping $productAkad)
    {
        $akadTypes = \App\Models\AkadType::where('active', true)->get();
        return view('product-akad.edit', compact('productAkad', 'akadTypes'));
    }

    public function update(Request $request, ProductAkadMapping $productAkad)
    {
        $validator = Validator::make($request->all(), [
            'product_code' => 'required|string|max:50|unique:product_akad_mappings,product_code,' . $productAkad->id,
            'product_name' => 'required|string|max:255',
            'akad_code' => 'required|string|exists:akad_types,kode_akad',
            'ead_rule_type' => 'required|in:MURABAHAH,MULTIJASA,MUSYARAKAH,IMBT',
            'active' => 'boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $akad = \App\Models\AkadType::where('kode_akad', $data['akad_code'])->first();
        $data['akad_name'] = $akad->nama_akad;

        $productAkad->update($data);
        ProductAkadMappingService::invalidateCache();

        return redirect()->route('product-akad.index')->with('success', 'Mapping berhasil diupdate');
    }

    public function destroy(ProductAkadMapping $productAkad)
    {
        $productAkad->delete();
        ProductAkadMappingService::invalidateCache();

        return redirect()->route('product-akad.index')->with('success', 'Mapping berhasil dihapus');
    }

    public function bulkDestroy(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer|exists:product_akad_mappings,id']);
        
        $count = ProductAkadMapping::whereIn('id', $validated['ids'])->delete();
        ProductAkadMappingService::invalidateCache();
        
        return redirect()->route('product-akad.index')->with('success', "{$count} mappings berhasil dihapus");
    }

    // API endpoint untuk resolve
    public function resolve(Request $request)
    {
        $productCode = $request->input('product_code');
        if (empty($productCode)) {
            return response()->json(['error' => 'product_code required'], 400);
        }

        return response()->json([
            'product_code' => $productCode,
            'akad_code' => ProductAkadMappingService::resolveAkadCode($productCode),
            'ead_rule_type' => ProductAkadMappingService::resolveEadRuleType($productCode),
        ]);
    }

    public function importForm(): View
    {
        return view('product-akad.import');
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
            $headerFields = ['product_code', 'product_name', 'akad_code', 'ead_rule_type', 'active', 'description'];

            $batchUpsert = function (array $rows) {
                $upsertData = [];
                $now = now();
                $akadMap = AkadType::pluck('nama_akad', 'kode_akad')->toArray();

                foreach ($rows as $row) {
                    $productCode = trim($row['product_code'] ?? '');
                    if (empty($productCode)) {
                        continue;
                    }

                    $akadCode = trim($row['akad_code'] ?? '');
                    $akadName = $akadMap[$akadCode] ?? null;

                    $upsertData[] = [
                        'product_code' => $productCode,
                        'product_name' => trim($row['product_name'] ?? ''),
                        'akad_code' => $akadCode,
                        'akad_name' => $akadName,
                        'ead_rule_type' => trim($row['ead_rule_type'] ?? 'MURABAHAH'),
                        'active' => filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                        'description' => trim($row['description'] ?? '') ?: null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (!empty($upsertData)) {
                    ProductAkadMapping::upsert($upsertData, ['product_code'], ['product_name', 'akad_code', 'akad_name', 'ead_rule_type', 'active', 'description', 'updated_at']);
                    ProductAkadMappingService::invalidateCache();
                }
            };

            if (in_array($extension, ['csv', 'txt'], true)) {
                $result = SpoutImportService::importCsv($fullPath, $headerFields, $batchUpsert, null, 2000);
            } else {
                $service = new SpoutImportService($fullPath);
                $service->setBatchSize(500);

                $headerMap = [
                    'A' => 'product_code',
                    'B' => 'product_name',
                    'C' => 'akad_code',
                    'D' => 'ead_rule_type',
                    'E' => 'active',
                    'F' => 'description',
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
