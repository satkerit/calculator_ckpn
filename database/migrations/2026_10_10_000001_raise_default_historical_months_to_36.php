<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Naikkan default window historis PD Net Flow menjadi 36 bulan (WAJIB).
 * Idempotent: hanya ubah default bila masih 24; down() mengembalikan ke 24.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stage_configs')) {
            Schema::table('stage_configs', function (Blueprint $table) {
                $table->integer('historical_months')->default(36)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stage_configs')) {
            Schema::table('stage_configs', function (Blueprint $table) {
                $table->integer('historical_months')->default(24)->change();
            });
        }
    }
};
