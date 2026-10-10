<?php

namespace App\Services\Bucket;

use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk assign bucket_no ke snapshot berdasarkan kriteria tunggakan.
 * Bucket 0 (Lancar/0 hari), 1-13 (tunggakan per 30 hari), 14 (Write-off).
 */
class BucketAssignmentService
{
    /**
     * Hitung bucket number untuk single snapshot.
     */
    public function calculateBucket(Snapshot $snapshot): int
    {
        if ($snapshot->write_off_flag) {
            return 14;
        }

        $dpd = (int) ($snapshot->days_past_due ?? 0);
        if ($dpd <= 0) {
            return 0;
        }

        return min(13, (int) ceil($dpd / 30));
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
                        WHEN write_off_flag = 1 THEN 14
                        WHEN days_past_due <= 0 THEN 0
                        WHEN days_past_due BETWEEN 1 AND 30 THEN 1
                        WHEN days_past_due BETWEEN 31 AND 60 THEN 2
                        WHEN days_past_due BETWEEN 61 AND 90 THEN 3
                        WHEN days_past_due BETWEEN 91 AND 120 THEN 4
                        WHEN days_past_due BETWEEN 121 AND 150 THEN 5
                        WHEN days_past_due BETWEEN 151 AND 180 THEN 6
                        WHEN days_past_due BETWEEN 181 AND 210 THEN 7
                        WHEN days_past_due BETWEEN 211 AND 240 THEN 8
                        WHEN days_past_due BETWEEN 241 AND 270 THEN 9
                        WHEN days_past_due BETWEEN 271 AND 300 THEN 10
                        WHEN days_past_due BETWEEN 301 AND 330 THEN 11
                        WHEN days_past_due BETWEEN 331 AND 360 THEN 12
                        ELSE 13
                    END
                "),
            ]);
    }

    public static function getBucketLabel(int $bucketNo): string
    {
        if ($bucketNo === 0) return '0 hari (Lancar)';
        if ($bucketNo === 14) return '> 360 + Write-off';
        if ($bucketNo === 13) return '> 360 hari';
        if ($bucketNo >= 1 && $bucketNo <= 12) {
            $min = ($bucketNo - 1) * 30 + 1;
            $max = $bucketNo * 30;
            return "{$min} - {$max} hari";
        }
        return 'Unknown';
    }
}
