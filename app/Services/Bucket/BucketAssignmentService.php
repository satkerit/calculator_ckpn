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
        $tglwoYm = $snapshot->tglwo ? substr((string) $snapshot->tglwo, 0, 6) : null;
        if ($snapshot->write_off_flag && $tglwoYm === (string) $snapshot->periode_snapshot) {
            return 14;
        }

        $dpd = (int) ($snapshot->days_past_due ?? 0);
        if ($dpd > 360) {
            return 14;
        }
        if ($dpd <= 0) {
            return 1;
        }

        return min(13, 1 + (int) ceil($dpd / 30));
    }

    /**
     * Batch update bucket_no via raw SQL untuk performa.
     */
    public function assignBucketsForPeriodFast(string $periodeYm): int
    {
        return DB::table('snapshot')
            ->where('periode_snapshot', $periodeYm)
            ->where('active_flag', true)
            ->update([
                'bucket_no' => DB::raw("
                    CASE
                        WHEN write_off_flag = 1 AND SUBSTRING(tglwo, 1, 6) = periode_snapshot THEN 14
                        WHEN days_past_due > 360 THEN 14
                        WHEN days_past_due <= 0 THEN 1
                        WHEN days_past_due BETWEEN 1 AND 30 THEN 2
                        WHEN days_past_due BETWEEN 31 AND 60 THEN 3
                        WHEN days_past_due BETWEEN 61 AND 90 THEN 4
                        WHEN days_past_due BETWEEN 91 AND 120 THEN 5
                        WHEN days_past_due BETWEEN 121 AND 150 THEN 6
                        WHEN days_past_due BETWEEN 151 AND 180 THEN 7
                        WHEN days_past_due BETWEEN 181 AND 210 THEN 8
                        WHEN days_past_due BETWEEN 211 AND 240 THEN 9
                        WHEN days_past_due BETWEEN 241 AND 270 THEN 10
                        WHEN days_past_due BETWEEN 271 AND 300 THEN 11
                        WHEN days_past_due BETWEEN 301 AND 330 THEN 12
                        WHEN days_past_due BETWEEN 331 AND 360 THEN 13
                        ELSE 14
                    END
                "),
            ]);
    }

    public static function getBucketLabel(int $bucketNo): string
    {
        if ($bucketNo === 1) return '0 hari (Lancar)';
        if ($bucketNo === 14) return '> 360 hari / Write-off';
        if ($bucketNo >= 2 && $bucketNo <= 13) {
            $min = ($bucketNo - 2) * 30 + 1;
            $max = ($bucketNo - 1) * 30;
            return "{$min} - {$max} hari";
        }
        return 'Unknown';
    }
}
