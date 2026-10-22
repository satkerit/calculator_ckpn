<?php

namespace App\Http\Controllers;

use App\Models\Snapshot;
use App\Models\Collateral;
use App\Services\SpoutImportService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    public function snapshotImportForm(): View
    {
        return view('import.snapshot');
    }

    public function collateralImportForm(): View
    {
        return view('import.collateral');
    }

    public function officeImportForm(): View
    {
        return view('import.office');
    }

    public function collateralSetupImportForm(): View
    {
        return view('import.collateral_setup');
    }

    /**
     * Upload historical snapshot dengan SSE realtime progress (Mendukung .csv, .xlsx, .xls)
     */
    public function uploadSnapshot(Request $request): StreamedResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:51200']);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $fullPath = Storage::path($file->store('temp'));

        return response()->stream(function () use ($fullPath, $extension) {
            $this->sse(['status' => 'starting', 'message' => 'Initializing...']);

            try {
                // Pre-count total rows untuk progress percentage
                $totalRows = $this->countFileRows($fullPath, $extension);

                $this->sse([
                    'status' => 'progress',
                    'current' => 0,
                    'total' => $totalRows,
                    'percentage' => 0,
                    'message' => "Total rows: {$totalRows}. Memulai import...",
                ]);

                // Cache master data
                $existingOffices = \App\Models\Office::pluck('office_code')->flip()->toArray();
                $missingOffices = [];

                $headerFields = [
                    'loan_number',
                    'product_code',
                    'office_code',
                    'usage_type',
                    'tglwo',
                    'effective_date',
                    'expired_date',
                    'outstanding_balance',
                    'principal_arrears',
                    'days_past_due',
                    'quality_rating',
                    'financing_contract_code',
                    'active_flag',
                    'write_off_flag',
                    'ppka_value',
                    'periode_snapshot',
                ];

                $totalProcessed = 0;
                $totalSuccess = 0;
                $totalFailed = 0;
                $errors = [];

                $batchUpsert = function (array $rows) use (
                    &$existingOffices,
                    &$missingOffices,
                    &$totalProcessed,
                    &$totalSuccess,
                    &$totalFailed,
                    &$errors,
                    $totalRows
                ) {
                    $now = now();
                    $snapshotUpserts = [];
                    $batchErrors = [];
                    $seenKeys = []; // loan_number + periode untuk detect duplicate

                    foreach ($rows as $rowData) {
                        $totalProcessed++;
                        $currentRow = $totalProcessed;

                        $loanNumber = trim((string) ($rowData['loan_number'] ?? ''));
                        $periode = trim((string) ($rowData['periode_snapshot'] ?? ''));
                        $officeCode = trim((string) ($rowData['office_code'] ?? ''));

                        if ($loanNumber === '' || $periode === '' || $officeCode === '') {
                            $errorMsg = "Empty field";
                            if ($loanNumber === '') $errorMsg .= " loan_number";
                            if ($periode === '') $errorMsg .= " periode_snapshot";
                            if ($officeCode === '') $errorMsg .= " office_code";

                            $batchErrors[] = [
                                'row' => $currentRow,
                                'error' => $errorMsg,
                                'data' => ['loan_number' => $loanNumber, 'office_code' => $officeCode]
                            ];
                            \Log::warning("Snapshot row skip: $errorMsg", $rowData);
                            $totalFailed++;
                            continue;
                        }

                        // Check duplicate loan_number + periode dalam batch upload
                        $duplicateKey = $loanNumber . '|' . $periode;
                        if (isset($seenKeys[$duplicateKey])) {
                            $batchErrors[] = [
                                'row' => $currentRow,
                                'error' => "Duplicate data: loan '$loanNumber' periode '$periode' sudah ada di row {$seenKeys[$duplicateKey]}",
                                'data' => ['loan_number' => $loanNumber, 'periode' => $periode]
                            ];
                            \Log::warning("Snapshot duplicate detected", ['loan_number' => $loanNumber, 'periode' => $periode, 'row' => $currentRow]);
                            $totalFailed++;
                            continue;
                        }
                        $seenKeys[$duplicateKey] = $currentRow;

                        // Validasi FK - enforce referential integrity
                        if (!isset($existingOffices[$officeCode])) {
                            $batchErrors[] = [
                                'row' => $currentRow,
                                'error' => "Office code '$officeCode' tidak ditemukan di master data",
                                'data' => ['office_code' => $officeCode, 'loan_number' => $loanNumber]
                            ];
                            \Log::warning("Snapshot FK error: office not found", ['office_code' => $officeCode]);
                            $missingOffices[$officeCode] = true;
                            $totalFailed++;
                            continue;
                        }

                        // Parse flags
                        $activeVal = strtolower(trim((string) ($rowData['active_flag'] ?? '1')));
                        $isActive = in_array($activeVal, ['1', 'true', 'active', 'aktif', 'yes'], true) || $activeVal === '';

                        $woVal = strtoupper(trim((string) ($rowData['write_off_flag'] ?? '0')));
                        $isWo = in_array($woVal, ['W', '1', 'TRUE', 'WO', 'WRITE_OFF', 'YES'], true);

                        // Quality rating
                        $qualityRating = trim((string) ($rowData['quality_rating'] ?? ''));
                        if ($qualityRating === '' || $qualityRating === '0') {
                            $qualityRating = '1';
                        } elseif ($qualityRating === 'WO' || $isWo) {
                            $qualityRating = '5';
                        } elseif (!in_array($qualityRating, ['1', '2', '3', '4', '5'], true)) {
                            $qualityRating = '1';
                        }

                        $effDate = SpoutImportService::parseDate($rowData['effective_date'] ?? null);
                        $expDate = SpoutImportService::parseDate($rowData['expired_date'] ?? null);

                        $snapshotUpserts[] = [
                            'loan_number' => $loanNumber,
                            'periode_snapshot' => $periode,
                            'product_code' => trim((string) ($rowData['product_code'] ?? '')),
                            'office_code' => $officeCode,
                            'usage_type' => trim((string) ($rowData['usage_type'] ?? null)),
                            'tglwo' => trim((string) ($rowData['tglwo'] ?? null)),
                            'effective_date' => $effDate ? $effDate->format('Y-m-d') : null,
                            'expired_date' => $expDate ? $expDate->format('Y-m-d') : null,
                            'outstanding_balance' => SpoutImportService::parseDecimal($rowData['outstanding_balance'] ?? 0),
                            'principal_arrears' => SpoutImportService::parseDecimal($rowData['principal_arrears'] ?? 0),
                            'days_past_due' => (int) ($rowData['days_past_due'] ?? 0),
                            'quality_rating' => $qualityRating,
                            'financing_contract_code' => $rowData['financing_contract_code'] ?? null,
                            'active_flag' => $isActive,
                            'write_off_flag' => $isWo,
                            'ppka_value' => SpoutImportService::parseDecimal($rowData['ppka_value'] ?? 0),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $totalSuccess++;
                    }

                    // Batch upsert
                    if (!empty($snapshotUpserts)) {
                        try {
                            $affectedRows = Snapshot::upsert(
                                $snapshotUpserts,
                                ['loan_number', 'periode_snapshot'],
                                [
                                    'product_code',
                                    'office_code',
                                    'usage_type',
                                    'tglwo',
                                    'effective_date',
                                    'expired_date',
                                    'outstanding_balance',
                                    'principal_arrears',
                                    'days_past_due',
                                    'quality_rating',
                                    'financing_contract_code',
                                    'active_flag',
                                    'write_off_flag',
                                    'ppka_value',
                                    'updated_at',
                                ]
                            );

                            \Log::info("Upsert success", ['affected_rows' => $affectedRows, 'batch_size' => count($snapshotUpserts)]);

                            $this->sse(['status' => 'log', 'type' => 'success', 'message' => "✅ Batch upsert: {$affectedRows} rows affected, " . count($snapshotUpserts) . " records"]);
                        } catch (\Throwable $e) {
                            \Log::error("Snapshot upsert batch error: " . $e->getMessage(), [
                                'sample_data' => array_slice($snapshotUpserts, 0, 2),
                                'sql' => $e instanceof \Illuminate\Database\QueryException ? $e->getSql() : null,
                            ]);

                            $this->sse(['status' => 'log', 'type' => 'error', 'message' => "❌ Database Error: " . $this->summarizeDbError($e, $snapshotUpserts)]);
                            $totalFailed += count($snapshotUpserts);
                            $totalSuccess -= count($snapshotUpserts);
                        }
                    }

                    // Flush validation errors di akhir batch
                    foreach ($batchErrors as $err) {
                        $errors[] = $err;
                        $this->sse(['status' => 'log', 'type' => 'error', 'message' => "❌ Row {$err['row']}: {$err['error']} | {$err['data']['loan_number']}"]);
                    }

                    // Send progress setiap batch selesai
                    $pct = $totalRows > 0 ? round(($totalProcessed / $totalRows) * 100, 1) : 0;
                    $this->sse([
                        'status' => 'progress',
                        'current' => $totalProcessed,
                        'total' => $totalRows,
                        'percentage' => $pct,
                        'message' => "Processing row {$totalProcessed} / {$totalRows}... ({$totalSuccess} success, {$totalFailed} failed)",
                    ]);
                };

                $this->runImport($fullPath, $extension, $headerFields, $batchUpsert);

                @unlink($fullPath);

                // Build completion message
                $errorMsg = '';
                if (!empty($missingOffices)) {
                    $errorMsg .= 'Missing offices: ' . implode(', ', array_slice(array_keys($missingOffices), 0, 5)) . (count($missingOffices) > 5 ? '...' : '') . '. ';
                }

                $this->sse([
                    'status' => 'complete',
                    'imported' => $totalSuccess,
                    'failed' => $totalFailed,
                    'errors' => array_slice($errors, 0, 100),
                    'message' => "Import complete: {$totalSuccess} imported, {$totalFailed} failed. {$errorMsg}",
                ]);
            } catch (\Throwable $e) {
                \Log::error("Snapshot upload error: " . $e->getMessage());
                $this->sse(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
            }
        }, $this->sseHeaders());
    }

    /**
     * Upload collateral data dengan SSE realtime progress (Mendukung .csv, .xlsx, .xls)
     */
    public function uploadCollateral(Request $request): StreamedResponse
    {
        $fields = ['loan_number', 'collateral_code', 'collateral_order', 'collateral_type', 'liquidity_value', 'estimated_value', 'valuation_date', 'active_flag'];

        return $this->streamImport($request, $fields, function (array $rows) {
            $now = now();
            $upsertData = [];

            foreach ($rows as $rowData) {
                $loanNumber = trim((string) ($rowData['loan_number'] ?? ''));
                $collateralCode = trim((string) ($rowData['collateral_code'] ?? ''));

                if ($loanNumber === '' || $collateralCode === '') {
                    continue;
                }

                $activeVal = strtolower(trim((string) ($rowData['active_flag'] ?? '1')));
                $valDate = SpoutImportService::parseDate($rowData['valuation_date'] ?? null);

                $upsertData[] = [
                    'loan_number' => $loanNumber,
                    'collateral_code' => $collateralCode,
                    'collateral_order' => (int) ($rowData['collateral_order'] ?? 1),
                    'collateral_type' => trim((string) ($rowData['collateral_type'] ?? '')),
                    'liquidity_value' => SpoutImportService::parseDecimal($rowData['liquidity_value'] ?? 0),
                    'estimated_value' => SpoutImportService::parseDecimal($rowData['estimated_value'] ?? 0),
                    'valuation_date' => $valDate ? $valDate->format('Y-m-d') : null,
                    'active_flag' => in_array($activeVal, ['1', 'true', 'active', 'aktif', 'yes'], true) || $activeVal === '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($upsertData)) {
                Collateral::upsert(
                    $upsertData,
                    ['loan_number', 'collateral_code'],
                    ['collateral_order', 'collateral_type', 'liquidity_value', 'estimated_value', 'valuation_date', 'active_flag', 'updated_at']
                );
            }
        });
    }

    /**
     * Upload office data dengan SSE realtime progress (Mendukung .csv, .xlsx, .xls)
     */
    public function uploadOffice(Request $request): StreamedResponse
    {
        $fields = ['office_code', 'office_name', 'office_type', 'active'];

        return $this->streamImport($request, $fields, function (array $rows) {
            $now = now();
            $upsertData = [];

            foreach ($rows as $rowData) {
                $officeCode = trim((string) ($rowData['office_code'] ?? ''));
                if ($officeCode === '') {
                    continue;
                }

                $activeVal = strtolower(trim((string) ($rowData['active'] ?? '1')));

                $upsertData[] = [
                    'office_code' => $officeCode,
                    'office_name' => trim((string) ($rowData['office_name'] ?? "Cabang {$officeCode}")),
                    'office_type' => trim((string) ($rowData['office_type'] ?? 'KP')),
                    'active' => in_array($activeVal, ['1', 'true', 'active', 'aktif', 'yes'], true) || $activeVal === '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($upsertData)) {
                \App\Models\Office::upsert(
                    $upsertData,
                    ['office_code'],
                    ['office_name', 'office_type', 'active', 'updated_at']
                );
            }
        });
    }

    /**
     * Upload collateral setup data dengan SSE realtime progress (Mendukung .csv, .xlsx, .xls)
     */
    public function uploadCollateralSetup(Request $request): StreamedResponse
    {
        $fields = ['collateral_type', 'collateral_ket', 'active'];

        return $this->streamImport($request, $fields, function (array $rows) {
            $now = now();
            $upsertData = [];

            foreach ($rows as $rowData) {
                $colType = trim((string) ($rowData['collateral_type'] ?? ''));
                if ($colType === '') {
                    continue;
                }

                $activeVal = strtolower(trim((string) ($rowData['active'] ?? '1')));

                $upsertData[] = [
                    'collateral_type' => $colType,
                    'collateral_ket' => trim((string) ($rowData['collateral_ket'] ?? '')),
                    'active' => in_array($activeVal, ['1', 'true', 'active', 'aktif', 'yes'], true) || $activeVal === '',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($upsertData)) {
                \App\Models\CollateralSetup::upsert(
                    $upsertData,
                    ['collateral_type'],
                    ['collateral_ket', 'active', 'updated_at']
                );
            }
        });
    }

    /* ---------------------------------------------------------------------
     *  Shared import helpers
     * --------------------------------------------------------------------- */

    /**
     * Streaming import wrapper untuk master data sederhana:
     * validasi file, jalankan CSV/XLSX import, kirim SSE progress & completion.
     */
    private function streamImport(Request $request, array $fields, \Closure $batchUpsert): StreamedResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt,xlsx,xls|max:20480']);

        $file = $request->file('file');
        $extension = strtolower($file->getClientOriginalExtension());
        $fullPath = Storage::path($file->store('temp'));

        return response()->stream(function () use ($fullPath, $extension, $fields, $batchUpsert) {
            $this->sse(['status' => 'starting', 'message' => "Importing {$fields[0]} data..."]);

            try {
                $result = $this->runImport($fullPath, $extension, $fields, $batchUpsert);

                @unlink($fullPath);

                $this->sse([
                    'status' => 'complete',
                    'imported' => $result['success'] ?? 0,
                    'failed' => $result['failed'] ?? 0,
                    'errors' => $result['errors'] ?? [],
                    'message' => 'Import complete: ' . ($result['success'] ?? 0) . ' imported, ' . ($result['failed'] ?? 0) . ' failed',
                ]);
            } catch (\Throwable $e) {
                \Log::error("Import error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
                $this->sse(['status' => 'error', 'message' => 'Error: ' . $e->getMessage()]);
            }
        }, $this->sseHeaders());
    }

    /**
     * Jalankan import CSV atau XLSX. Header map kolom Excel (A, B, C, ...)
     * diturunkan otomatis dari daftar field.
     */
    private function runImport(string $fullPath, string $extension, array $fields, \Closure $batchUpsert): array
    {
        if (in_array($extension, ['csv', 'txt'], true)) {
            return SpoutImportService::importCsv($fullPath, $fields, $batchUpsert, null, 1000);
        }

        $service = new SpoutImportService($fullPath);
        $service->setBatchSize(500);

        $headerMap = array_combine(range('A', chr(ord('A') + count($fields) - 1)), $fields);

        $buffer = [];
        $result = $service->import(
            $headerMap,
            function (array $rowData) use (&$buffer, $batchUpsert) {
                $buffer[] = $rowData;
                if (count($buffer) >= 500) {
                    $batchUpsert($buffer);
                    $buffer = [];
                }
            },
            null
        );

        if (!empty($buffer)) {
            $batchUpsert($buffer);
        }

        return $result ?? [];
    }

    /**
     * Hitung total data rows (tanpa header) untuk progress bar.
     */
    private function countFileRows(string $fullPath, string $extension): int
    {
        if (in_array($extension, ['csv', 'txt'], true)) {
            $handle = fopen($fullPath, 'r');
            if (!$handle) {
                return 0;
            }
            $total = 0;
            while (!feof($handle)) {
                fgets($handle);
                $total++;
            }
            fclose($handle);

            return max(0, $total - 1); // kurangi header row
        }

        // XLSX: scan satu kali untuk count (hanya sheet pertama)
        try {
            $reader = new \OpenSpout\Reader\XLSX\Reader();
            $reader->open($fullPath);
            $total = 0;
            $isFirst = true;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if ($isFirst) {
                        $isFirst = false;
                        continue;
                    }
                    foreach ($row->getCells() as $cell) {
                        if ($cell->getValue() !== null && $cell->getValue() !== '') {
                            $total++;
                            break;
                        }
                    }
                }
                break;
            }
            $reader->close();

            return $total;
        } catch (\Throwable) {
            return 0; // fallback: tidak diketahui
        }
    }

    /**
     * Ringkas error DB jadi pesan pendek untuk SSE.
     */
    private function summarizeDbError(\Throwable $e, array $sampleRows): string
    {
        $msg = $e->getMessage();

        if (preg_match('/Column \'(\w+)\'/', $msg, $m)) {
            return "Column '{$m[1]}' error (value: " . ($sampleRows[0][$m[1]] ?? 'NULL') . ")";
        }
        if (str_contains($msg, 'foreign key constraint fails')) {
            return 'Foreign key constraint - master data tidak ada';
        }
        if (str_contains($msg, 'Duplicate entry')) {
            return 'Duplicate entry - data sudah ada di database';
        }

        return strlen($msg) > 80 ? substr($msg, 0, 80) . '...' : $msg;
    }

    /**
     * Kirim satu SSE event.
     */
    private function sse(array $payload): void
    {
        echo 'data: ' . json_encode($payload) . "\n\n";
        ob_flush();
        flush();
    }

    private function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];
    }

    public function downloadTemplate(string $type)
    {
        $templates = [
            'snapshot' => [
                'Snapshot_Template.xlsx',
                ['loan_number', 'product_code', 'office_code', 'usage_type', 'tglwo', 'effective_date', 'expired_date', 'outstanding_balance', 'principal_arrears', 'days_past_due', 'quality_rating', 'financing_contract_code', 'active_flag', 'write_off_flag', 'ppka_value', 'periode_snapshot'],
                [
                    ['L001', 'MURABAHAH', '001', '010', '20240101', '2023-01-01', '2026-01-01', 95000000, 0, 0, '1', 'KTR001', 1, '0', 0, '202401'],
                    ['L002', 'MUSYARAKAH', '001', '020', '20240601', '2023-06-01', '2025-06-01', 150000000, 5000000, 45, '2', 'KTR002', 1, 'W', 0, '202401'],
                ],
            ],
            'collateral' => [
                'Collateral_Template.xlsx',
                ['loan_number', 'collateral_code', 'collateral_order', 'collateral_type', 'liquidity_value', 'estimated_value', 'valuation_date', 'active_flag'],
                [
                    ['L001', 'CLT001', 1, '17', 50000000, 60000000, '2024-01-01', 1],
                    ['L002', 'CLT002', 1, '18', 100000000, 120000000, '2024-01-01', 1],
                ],
            ],
            'office' => [
                'Office_Template.xlsx',
                ['office_code', 'office_name', 'office_type', 'active'],
                [
                    ['001', 'Kantor Pusat', 'KP', 1],
                    ['002', 'Kantor Cabang', 'KC', 1],
                ],
            ],
            'akad_type' => [
                'AkadType_Template.xlsx',
                ['kode_akad', 'nama_akad', 'kategori', 'ead_rule_type', 'requires_due_date', 'active'],
                [
                    ['01', 'Murabahah', 'jual_beli', 'outstanding_balance', 1, 1],
                    ['02', 'Musyarakah', 'bagi_hasil', 'principal_arrears_conditional', 1, 1],
                ],
            ],
            'product_akad' => [
                'ProductAkad_Template.xlsx',
                ['product_code', 'product_name', 'akad_code', 'ead_rule_type', 'active', 'description'],
                [
                    ['MURABAHAH', 'Pembiayaan Murabahah', '01', 'MURABAHAH', 1, 'Produk Jual Beli'],
                    ['MUSYARAKAH', 'Pembiayaan Musyarakah', '02', 'MUSYARAKAH', 1, 'Produk Bagi Hasil'],
                ],
            ],
            'collateral_setup' => [
                'CollateralSetup_Template.xlsx',
                ['collateral_type', 'collateral_ket', 'active'],
                [
                    ['17', 'GIRIK', 1],
                    ['18', 'SHM', 1],
                ],
            ],
        ];

        if (!isset($templates[$type])) {
            abort(400, "Invalid template type: $type");
        }

        [$filename, $headers, $sampleData] = $templates[$type];

        $tempFile = tempnam(sys_get_temp_dir(), 'template_');

        $writer = new \OpenSpout\Writer\XLSX\Writer();
        $writer->openToFile($tempFile);
        $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($headers));
        foreach ($sampleData as $data) {
            $writer->addRow(\OpenSpout\Common\Entity\Row::fromValues($data));
        }
        $writer->close();

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
