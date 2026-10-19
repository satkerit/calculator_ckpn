<?php

namespace App\Services\Bucket;

use App\Models\Snapshot;
use Illuminate\Support\Facades\DB;

/**
 * Service untuk assign bucket_no ke snapshot berdasarkan kriteria tunggakan.
 * bucket_no = urutan bucket (1 = Lancar/0 hari, 2-13 = tunggakan per 30 hari,
 * 14 = DPD > 360 ATAU pembiayaan write-off/hapus buku pada periode snapshot).
 * Aturan: bucket 331-360 hari TIDAK memuat pembiayaan write-off — semua WO
 * masuk bucket terakhir (>360), bersama pembiayaan aktif ber-DPD > 360.
 */
class BucketAssignmentService
{
    /**
     * Hitung bucket number untuk single snapshot.
     * Bucket terakhir untuk seluruh pembiayaan write-off (write_off_flag = 1),
     * bukan hanya yang tglwo-nya di periode snapshot.
     */
    public function calculateBucket(Snapshot $snapshot): int
    {
        // Load bucket definitions dinamis, urutkan berdasarkan urutan bucket
        $buckets = DB::table('bucket_definitions')
            ->where('active', true)
            ->orderBy('urutan', 'asc')
            ->get()
            ->toArray();

        if (empty($buckets)) {
            return 1;
        }

        $lastBucket = end($buckets);

        // Write-off/hapus buku → bucket terakhir (>360), apa pun DPD-nya
        if ($snapshot->write_off_flag) {
            return (int) $lastBucket->urutan;
        }

        $dpd = (int) ($snapshot->days_past_due ?? 0);

        if ($dpd <= 0) {
            // Bucket pertama (lancar)
            return (int) $buckets[0]->urutan;
        }

        // Cocokkan range hari_min - hari_max
        foreach ($buckets as $bucket) {
            if ($bucket->hari_min == 0 && $bucket->hari_max == 0) {
                continue; // Lancar, sudah ditangani
            }
            if ($bucket->hari_max === null || (int) $bucket->hari_max >= 99999) {
                // Tanpa batas atas
                if ($dpd >= $bucket->hari_min) {
                    return (int) $bucket->urutan;
                }
                continue;
            }
            if ($dpd >= $bucket->hari_min && $dpd <= $bucket->hari_max) {
                return (int) $bucket->urutan;
            }
        }

        // Melebihi semua range → bucket terakhir
        return (int) $lastBucket->urutan;
    }

    /**
     * Batch update bucket_no via raw SQL untuk performa.
     * Build CASE WHEN dinamis dari bucket_definitions.
     */
    public function assignBucketsForPeriodFast(string $periodeYm): int
    {
        // Load bucket config ordered by urutan bucket ascending
        $buckets = DB::table('bucket_definitions')
            ->where('active', true)
            ->orderBy('urutan', 'asc')
            ->get()
            ->toArray();

        if (empty($buckets)) {
            return 0; // Tidak ada bucket definition
        }

        $lastBucketNo = (int) end($buckets)->urutan;

        // Write-off/hapus buku → bucket terakhir (>360), apa pun DPD-nya.
        // Mencakup pembiayaan aktif ber-DPD > 360 maupun WO pada periode tsb.
        $caseWhen = "CASE WHEN write_off_flag = 1 THEN {$lastBucketNo}";

        foreach ($buckets as $bucket) {
            $bucketNo = (int) $bucket->urutan;
            if ($bucket->hari_min == 0 && $bucket->hari_max == 0) {
                // Bucket lancar (0 hari)
                $caseWhen .= " WHEN days_past_due <= 0 THEN {$bucketNo}";
            } elseif ($bucket->hari_max === null || (int) $bucket->hari_max >= 99999) {
                // Bucket tanpa batas atas
                $caseWhen .= " WHEN days_past_due >= {$bucket->hari_min} THEN {$bucketNo}";
            } else {
                // Bucket dengan range hari
                $caseWhen .= " WHEN days_past_due BETWEEN {$bucket->hari_min} AND {$bucket->hari_max} THEN {$bucketNo}";
            }
        }

        // Bucket terakhir untuk DPD > max
        $caseWhen .= " ELSE {$lastBucketNo} END";

        return DB::table('snapshot')
            ->where('periode_snapshot', $periodeYm)
            ->where('active_flag', true)
            ->update([
                'bucket_no' => DB::raw($caseWhen),
            ]);
    }

    public static function getBucketLabel(int $bucketNo): string
    {
        // bucket_no di seluruh sistem = urutan bucket (1..14), bukan ID tabel
        $bucket = DB::table('bucket_definitions')
            ->where('urutan', $bucketNo)
            ->where('active', true)
            ->first();

        if (!$bucket) {
            return 'Unknown';
        }

        if ($bucket->hari_min == 0 && $bucket->hari_max == 0) {
            return '0 hari (Lancar)';
        }

        if ($bucket->hari_max === null || (int) $bucket->hari_max >= 99999) {
            return '> ' . $bucket->hari_min . ' hari / Write-Off';
        }

        return "{$bucket->hari_min} - {$bucket->hari_max} hari";
    }
}
