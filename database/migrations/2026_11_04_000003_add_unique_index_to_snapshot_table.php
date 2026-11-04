<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wajib untuk `Snapshot::upsert(['loan_number', 'periode_snapshot'])`:
     * tanpa unique index, MySQL memperlakukan upsert sebagai INSERT biasa
     * sehingga re-import periode yang sama menduplikasi baris (EAD/CKPN menggelembung).
     */
    public function up(): void
    {
        if (! Schema::hasTable('snapshot')) {
            return;
        }

        $this->removeDuplicates();

        if (! $this->hasUniqueKey()) {
            Schema::table('snapshot', function (Blueprint $table) {
                $table->unique(['loan_number', 'periode_snapshot'], 'snapshot_loan_period_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('snapshot') && $this->hasUniqueKey()) {
            Schema::table('snapshot', function (Blueprint $table) {
                $table->dropUnique('snapshot_loan_period_unique');
            });
        }
    }

    /**
     * Simpan satu baris per (loan_number, periode_snapshot), yaitu baris terbaru.
     * Chunked agar tidak memuat seluruh tabel ke memori.
     */
    private function removeDuplicates(): void
    {
        $duplicateKeys = DB::table('snapshot')
            ->select('loan_number', 'periode_snapshot')
            ->groupBy('loan_number', 'periode_snapshot')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateKeys->chunk(200) as $chunk) {
            DB::transaction(function () use ($chunk) {
                foreach ($chunk as $key) {
                    $match = [
                        'loan_number' => $key->loan_number,
                        'periode_snapshot' => $key->periode_snapshot,
                    ];

                    $keep = DB::table('snapshot')
                        ->where($match)
                        ->orderByDesc('updated_at')
                        ->orderByDesc('created_at')
                        ->first();

                    if ($keep === null) {
                        continue;
                    }

                    DB::table('snapshot')->where($match)->delete();
                    DB::table('snapshot')->insert((array) $keep);
                }
            });
        }
    }

    private function hasUniqueKey(): bool
    {
        try {
            foreach (Schema::getIndexes('snapshot') as $index) {
                if ($index['unique'] === true && $index['columns'] === ['loan_number', 'periode_snapshot']) {
                    return true;
                }
            }
        } catch (\Throwable) {
            // Driver tanpa introspeksi indeks: biarkan CREATE INDEX melaporkan duplikat.
            return false;
        }

        return false;
    }
};
