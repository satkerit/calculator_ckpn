<?php

namespace App\Services;

use Closure;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use OpenSpout\Reader\CSV\Reader as CSVReader;
use Carbon\Carbon;

class SpoutImportService
{
    private string $filePath;
    private int $batchSize = 500;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;
        return $this;
    }

    /**
     * Parse tanggal dari format Excel/string aman
     */
    public static function parseDate($value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        // Convert ke string untuk check pattern dulu
        $str = trim((string) $value);
        
        // Check yyyymmdd string format first (prioritas)
        if (preg_match('/^\d{8}$/', $str)) {
            try {
                return Carbon::createFromFormat('Ymd', $str);
            } catch (\Throwable $e) {
                // Fallback kalau invalid date
            }
        }

        // Excel numeric date (biasanya < 50000, e.g. 45000 = 2023-02-14)
        if (is_numeric($value) && $value < 100000) {
            try {
                $days = (int) $value;
                return Carbon::createFromDate(1900, 1, 1)->addDays($days - 2);
            } catch (\Throwable $e) {
                // fallback
            }
        }

        // Fallback: parse string date format lain (YYYY-MM-DD, etc)
        try {
            return Carbon::parse($str);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Sanitasi format angka/desimal
     */
    public static function parseDecimal($value, float $default = 0.0): float
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^\d,.-]/', '', (string) $value);
        if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
            $cleaned = str_replace(',', '', $cleaned);
        } elseif (strpos($cleaned, ',') !== false) {
            $cleaned = str_replace(',', '.', $cleaned);
        }

        return is_numeric($cleaned) ? (float) $cleaned : $default;
    }

    /**
     * Import CSV stream dengan batch callback
     * @param array $headerMap ['loan_number', 'product_code', ...]
     * @param Closure $batchProcessor function(array $batchRows)
     * @param Closure|null $progressCallback function($current, $total, $percentage)
     * @param int $batchSize default 2000
     */
    public static function importCsv(
        string $filePath,
        array $headerMap,
        Closure $batchProcessor,
        ?Closure $progressCallback = null,
        int $batchSize = 2000
    ): array {
        $handle = fopen($filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Tidak dapat membuka file CSV: {$filePath}");
        }

        try {
            // Deteksi delimiter
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new \RuntimeException("File CSV kosong");
            }
            $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
            rewind($handle);

            // Lewati header
            fgetcsv($handle, 0, $delimiter);

            $success = 0;
            $failed = 0;
            $errors = [];
            $batch = [];
            $currentRow = 1;

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $currentRow++;
                if (empty(array_filter($row))) {
                    continue;
                }

                $mapped = [];
                $colIdx = 0;
                foreach ($headerMap as $field) {
                    $mapped[$field] = isset($row[$colIdx]) ? trim((string) $row[$colIdx]) : '';
                    $colIdx++;
                }

                $batch[] = $mapped;

                if (count($batch) >= $batchSize) {
                    try {
                        $batchProcessor($batch);
                        $success += count($batch);
                    } catch (\Throwable $e) {
                        $failed += count($batch);
                        $errors[] = ['row' => $currentRow, 'error' => $e->getMessage()];
                        \Log::error("CSV Batch Error row {$currentRow}: " . $e->getMessage());
                    }

                    $batch = [];

                    if ($progressCallback) {
                        $progressCallback($currentRow, 0, 0);
                    }
                }
            }

            // Flush buffer terakhir
            if (!empty($batch)) {
                try {
                    $batchProcessor($batch);
                    $success += count($batch);
                } catch (\Throwable $e) {
                    $failed += count($batch);
                    $errors[] = ['row' => $currentRow, 'error' => $e->getMessage()];
                    \Log::error("CSV Final Batch Error: " . $e->getMessage());
                }
            }

            if ($progressCallback) {
                $progressCallback($currentRow, $currentRow, 100.0);
            }

            return [
                'success' => $success,
                'failed' => $failed,
                'errors' => $errors,
                'total' => $success + $failed,
            ];

        } finally {
            fclose($handle);
        }
    }

    /**
     * Import Excel file dengan OpenSpout (XLSX, XLS, ODS)
     * @param array $headerMap ['A' => 'loan_number', 'B' => 'product_code', ...]
     * @param Closure $processor function($row, $index)
     * @param Closure|null $progressCallback function($current, $total, $percentage)
     */
    public function import(array $headerMap, Closure $processor, ?Closure $progressCallback = null): array
    {
        try {
            $reader = new XLSXReader();
            $reader->open($this->filePath);

            $success = 0;
            $failed = 0;
            $errors = [];
            $batch = [];
            $rowIndex = 0;

            $isFirstRow = true;
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rowIndex++;

                    // Skip header row
                    if ($isFirstRow) {
                        $isFirstRow = false;
                        continue;
                    }

                    $cells = $row->getCells();
                    $rowData = [];

                    $colIdx = 0;
                    foreach ($headerMap as $excelCol => $modelField) {
                        $value = isset($cells[$colIdx]) ? $cells[$colIdx]->getValue() : null;
                        $rowData[$modelField] = trim((string) ($value ?? ''));
                        $colIdx++;
                    }

                    if (empty(array_filter($rowData))) {
                        continue;
                    }

                    try {
                        $batch[] = ['data' => $rowData, 'row' => $rowIndex];

                        if (count($batch) >= $this->batchSize) {
                            foreach ($batch as $item) {
                                try {
                                    $processor($item['data'], $item['row']);
                                    $success++;
                                } catch (\Throwable $e) {
                                    $failed++;
                                    $errors[] = [
                                        'row' => $item['row'],
                                        'error' => $e->getMessage(),
                                        'data' => $item['data'],
                                    ];
                                    \Log::error("Excel import error row {$item['row']}: " . $e->getMessage());
                                }
                            }

                            $batch = [];

                            if ($progressCallback) {
                                $progressCallback($rowIndex, 0, 0);
                            }
                        }
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'row' => $rowIndex,
                            'error' => $e->getMessage(),
                            'data' => $rowData,
                        ];
                        \Log::error("Excel row processing error: " . $e->getMessage());
                    }
                }
            }

            // Flush remaining batch
            if (!empty($batch)) {
                foreach ($batch as $item) {
                    try {
                        $processor($item['data'], $item['row']);
                        $success++;
                    } catch (\Throwable $e) {
                        $failed++;
                        $errors[] = [
                            'row' => $item['row'],
                            'error' => $e->getMessage(),
                            'data' => $item['data'],
                        ];
                        \Log::error("Excel import error row {$item['row']}: " . $e->getMessage());
                    }
                }
            }

            $reader->close();

            return [
                'success' => $success,
                'failed' => $failed,
                'errors' => $errors,
                'total' => $success + $failed,
            ];

        } catch (\Throwable $e) {
            \Log::error("Spout import failed: " . $e->getMessage());
            return [
                'success' => 0,
                'failed' => 0,
                'errors' => [['error' => $e->getMessage()]],
                'total' => 0,
            ];
        }
    }
}
