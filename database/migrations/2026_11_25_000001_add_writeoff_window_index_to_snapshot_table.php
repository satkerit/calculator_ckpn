<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index window write-off untuk LGD (ER & CS).
 *
 * Query LGD memfilter `write_off_flag = 1 AND SUBSTRING(tglwo, 1, 6) BETWEEN ...`
 * pada tabel snapshot (526k+ baris). Tanpa index, setiap run LGD memicu full
 * table scan berulang (LGD ER, LGD CS, export). Index komposit ini mempersempit
 * scan ke baris write-off saja (±1.8k baris).
 *
 * Index per-dimensi grup (office/akad/usage) sengaja TIDAK ditambahkan: query
 * historis PD sudah terfilter `periode_snapshot` (index existing) sehingga hanya
 * membaca ±7k baris per periode — tambahan index justru membebani proses import.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            $table->index(['write_off_flag', 'tglwo'], 'snapshot_wo_tglwo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            $table->dropIndex('snapshot_wo_tglwo_idx');
        });
    }
};
