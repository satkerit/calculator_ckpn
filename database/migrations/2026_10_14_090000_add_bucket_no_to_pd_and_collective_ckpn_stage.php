<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PD Net Flow mengikuti SEGMENTASI + BUCKET: satu baris PD per kombinasi
 * segmen × bucket (rate dari compound flow `source_bucket` yang sesuai).
 * CKPN Kolektif pun dipecah per segmen × bucket agar konsisten
 * (CKPN = EAD_bucket × PD_rate_bucket × LGD).
 *
 * Catatan penting: pada MySQL, unique index `stage_pd_unique` /
 * `coll_ckpn_unique` dipakai oleh foreign key `assessment_period_id`,
 * sehingga index tidak dapat di-drop sebelum FK di-drop terlebih dahulu.
 * Karena itu urutan operasinya: drop FK → drop unique → add unique → add FK.
 * Migrasi dibuat idempoten agar aman dijalankan ulang bila sempat gagal
 * di tengah jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuildUnique(
            table: 'stage_pd_results',
            oldIndex: 'stage_pd_unique',
            oldColumns: ['assessment_period_id', 'group_key', 'method'],
            newColumns: ['assessment_period_id', 'group_key', 'method', 'bucket_no'],
        );

        $this->rebuildUnique(
            table: 'stage_collective_ckpn',
            oldIndex: 'coll_ckpn_unique',
            oldColumns: ['assessment_period_id', 'group_key'],
            newColumns: ['assessment_period_id', 'group_key', 'bucket_no'],
        );
    }

    public function down(): void
    {
        $this->rebuildUnique(
            table: 'stage_pd_results',
            oldIndex: 'stage_pd_unique',
            oldColumns: ['assessment_period_id', 'group_key', 'method', 'bucket_no'],
            newColumns: ['assessment_period_id', 'group_key', 'method'],
            dropBucket: true,
        );

        $this->rebuildUnique(
            table: 'stage_collective_ckpn',
            oldIndex: 'coll_ckpn_unique',
            oldColumns: ['assessment_period_id', 'group_key', 'bucket_no'],
            newColumns: ['assessment_period_id', 'group_key'],
            dropBucket: true,
        );
    }

    /**
     * Pastikan kolom bucket_no ada, lalu bangun ulang unique index memakai
     * kolom baru. FK assessment_period_id di-drop lebih dulu (bila ada) lalu
     * dibuat ulang karena index lama menopang FK tersebut.
     */
    private function rebuildUnique(
        string $table,
        string $oldIndex,
        array $oldColumns,
        array $newColumns,
        bool $dropBucket = false,
    ): void {
        if (! Schema::hasTable($table)) {
            return;
        }

        $fkName = $this->foreignKeyName($table);

        if (! Schema::hasColumn($table, 'bucket_no')) {
            Schema::table($table, function (Blueprint $t) {
                $t->unsignedTinyInteger('bucket_no')->default(1)->after('group_key');
            });
        }

        // FK bergantung pada index lama → drop FK dulu.
        $this->dropForeignKeyByName($table, $fkName);

        if ($this->indexExists($table, $oldIndex)) {
            Schema::table($table, function (Blueprint $t) use ($oldIndex) {
                $t->dropUnique($oldIndex);
            });
        }

        Schema::table($table, function (Blueprint $t) use ($oldIndex, $newColumns) {
            $t->unique($newColumns, $oldIndex);
        });

        // Buat ulang FK (butuh index yang mengawali kolom assessment_period_id —
        // sudah dipenuhi oleh unique di atas).
        $this->addForeignKey($table, $fkName);

        if ($dropBucket) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('bucket_no');
            });
        }
    }

    private function foreignKeyName(string $table): string
    {
        return "{$table}_assessment_period_id_foreign";
    }

    /**
     * Cek keberadaan index via Schema API (driver-agnostic: MySQL & SQLite).
     */
    private function indexExists(string $table, string $indexName): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $indexName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek keberadaan foreign key via Schema API (driver-agnostic).
     */
    private function foreignKeyExists(string $table, string $fkName): bool
    {
        foreach (Schema::getForeignKeys($table) as $fk) {
            if (($fk['name'] ?? null) === $fkName) {
                return true;
            }
        }

        return false;
    }

    private function dropForeignKeyByName(string $table, string $fkName): void
    {
        if (! $this->foreignKeyExists($table, $fkName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($fkName) {
            $t->dropForeign($fkName);
        });
    }

    private function addForeignKey(string $table, string $fkName): void
    {
        if ($this->foreignKeyExists($table, $fkName)) {
            return;
        }

        Schema::table($table, function (Blueprint $t) use ($fkName) {
            $t->foreign('assessment_period_id', $fkName)
                ->references('id')->on('assessment_periods')
                ->cascadeOnDelete();
        });
    }
};
