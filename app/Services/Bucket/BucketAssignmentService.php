<?php

namespace App\Services\Bucket;

use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk assign bucket_no ke snapshot berdasarkan kriteria tunggakan.
 * Bucket 1 (Lancar/0 hari), 2-13 (tunggakan per 30 hari), 14 (DPD > 360
 * atau hapus buku yang dijalankan pada periode snapshot tersebut).
 */
class BucketAssignmentService
{
    /**
     * Hitung bucket number untuk single snapshot.
     * Bucket 14 hanya untuk WO yang tanggal write-off-nya (tglwo) berada
     * pada periode snapshot yang sama — flag WO yang menetap pada periode
     * berikutnya TIDAK otomatis masuk bucket 14.
     */
    public function calculateBucket(Snapshot $snapshot): int
    {
        // Load bucket definitions dinamis
        $buckets = DB::table('bucket_definitions')
            ->where('active', true)
            ->orderBy('id', 'asc')
            ->get()
            ->toArray();

        if (empty($buckets)) {
            return 1;
        }

        $maxBucket = end($buckets);

        // Write-off di periode sama → bucket terakhir
        $tglwoYm = $snapshot->tglwo ? substr((string) $snapshot->tglwo, 0, 6) : null;
        if ($snapshot->write_off_flag && $tglwoYm === (string) $snapshot->periode_snapshot) {
            return $maxBucket->id;
        }

        $dpd = (int) ($snapshot->days_past_due ?? 0);

        if ($dpd <= 0) {
            // Bucket pertama (lancar)
            return $buckets[0]->id;
        }

        // Cocokkan range hari_min - hari_max
        foreach ($buckets as $bucket) {
            if ($bucket->hari_min == 0 && $bucket->hari_max == 0) {
                continue; // Lancar, sudah ditangani
            }
            if ($bucket->hari_max === null) {
                // Tanpa batas atas
                if ($dpd >= $bucket->hari_min) {
                    return $bucket->id;
                }
                continue;
            }
            if ($dpd >= $bucket->hari_min && $dpd <= $bucket->hari_max) {
                return $bucket->id;
            }
        }

        // Melebihi semua range → bucket terakhir
        return $maxBucket->id;
    }

    /**
     * Batch update bucket_no via raw SQL untuk performa.
     * Build CASE WHEN dinamis dari bucket_definitions.
     */
    public function assignBucketsForPeriodFast(string $periodeYm): int
    {
        // Load bucket config ordered by hari_min ascending
        $buckets = DB::table('bucket_definitions')
            ->where('active', true)
            ->orderBy('hari_min', 'asc')
            ->get()
            ->toArray();

        if (empty($buckets)) {
            return 0; // Tidak ada bucket definition
        }

        // Build CASE WHEN dinamis
        $caseWhen = "CASE WHEN write_off_flag = 1 AND SUBSTRING(tglwo, 1, 6) = periode_snapshot THEN " . end($buckets)->id;
        
        foreach ($buckets as $bucket) {
            if ($bucket->hari_min == 0 && $bucket->hari_max == 0) {
                // Bucket lancar (0 hari)
                $caseWhen .= " WHEN days_past_due <= 0 THEN {$bucket->id}";
            } elseif ($bucket->hari_max === null) {
                // Bucket tanpa batas atas
                $caseWhen .= " WHEN days_past_due >= {$bucket->hari_min} THEN {$bucket->id}";
            } else {
                // Bucket dengan range hari
                $caseWhen .= " WHEN days_past_due BETWEEN {$bucket->hari_min} AND {$bucket->hari_max} THEN {$bucket->id}";
            }
        }
        
        // Bucket terakhir untuk DPD > max
        $maxBucket = end($buckets);
        $caseWhen .= " ELSE {$maxBucket->id} END";

        return DB::table('snapshot')
            ->where('periode_snapshot', $periodeYm)
            ->where('active_flag', true)
            ->update([
                'bucket_no' => DB::raw($caseWhen),
            ]);
    }

    public static function getBucketLabel(int $bucketId): string
    {
        $bucket = DB::table('bucket_definitions')
            ->where('id', $bucketId)
            ->where('active', true)
            ->first();

        if (!$bucket) {
            return 'Unknown';
        }

        if ($bucket->hari_min == 0 && $bucket->hari_max == 0) {
            return '0 hari (Lancar)';
        }

        if ($bucket->hari_max === null) {
            return '≥ ' . $bucket->hari_min . ' hari (tanpa batas)';
        }

        if ($bucket->hari_max >= 360) {
            return '> ' . $bucket->hari_min . ' hari / Write-off';
        }

        return "{$bucket->hari_min} - {$bucket->hari_max} hari";
    }
}
