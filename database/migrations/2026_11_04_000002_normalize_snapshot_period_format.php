<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Samakan format `snapshot.periode_snapshot` menjadi YYYYMM.
     *
     * Seluruh pembacaan periode memakai kesamaan persis `where('periode_snapshot', 'YYYYMM')`,
     * sehingga baris berformat tanggal (mis. 2021-01-01) tidak pernah ikut terhitung.
     */
    public function up(): void
    {
        if (! Schema::hasTable('snapshot')) {
            return;
        }

        $legacyValues = DB::table('snapshot')
            ->select('periode_snapshot')
            ->distinct()
            ->where('periode_snapshot', 'like', '%-%')
            ->pluck('periode_snapshot');

        foreach ($legacyValues as $value) {
            $normalized = $this->toYm((string) $value);

            if ($normalized === null || $normalized === $value) {
                continue;
            }

            DB::table('snapshot')
                ->where('periode_snapshot', $value)
                ->update(['periode_snapshot' => $normalized]);
        }
    }

    public function down(): void
    {
        // Tidak ada pembalikan: format tanggal lama tidak bisa direkonstruksi
        // tanpa kehilangan info hari, dan YYYYMM adalah format kanonik aplikasi.
    }

    /**
     * YYYY-MM-DD | YYYY-MM | YYYYMMDD → YYYYMM. Null bila tidak dikenali.
     */
    private function toYm(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^([0-9]{4})-([0-9]{1,2})(?:-[0-9]{1,2})?$/', $value, $m) === 1) {
            $month = (int) $m[2];

            return $month >= 1 && $month <= 12
                ? $m[1].str_pad((string) $month, 2, '0', STR_PAD_LEFT)
                : null;
        }

        if (preg_match('/^([0-9]{4})([0-9]{2})[0-9]{2}$/', $value, $m) === 1) {
            $month = (int) $m[2];

            return $month >= 1 && $month <= 12 ? $m[1].$m[2] : null;
        }

        return null;
    }
};
