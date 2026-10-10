<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ganti kriteria CKPN Individual dari threshold nominal menjadi:
 * debitur NPL dengan TOP N outstanding terbesar.
 *
 * NPL ditentukan dari KUALITAS pembiayaan (kolektibilitas), bukan DPD:
 * kualitas 3,4,5 = NPL (default, dapat dikonfigurasi via `npl_ratings`).
 *
 * Idempotent: aman dijalankan ulang dan membersihkan kolom percobaan lama
 * (`npl_dpd_min`, `npl_rating_min`). Kolom `ead_individual_threshold`
 * dibiarkan (tidak di-drop) agar tidak ada kehilangan data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bersihkan kolom DPD/rating-min dari iterasi sebelumnya bila ada.
        Schema::table('stage_configs', function (Blueprint $table) {
            $drop = array_filter(
                ['npl_dpd_min', 'npl_rating_min'],
                fn ($c) => Schema::hasColumn('stage_configs', $c)
            );
            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        Schema::table('stage_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('stage_configs', 'individual_top_n')) {
                $table->unsignedInteger('individual_top_n')->default(20)->after('ead_individual_threshold');
            }
            if (! Schema::hasColumn('stage_configs', 'npl_ratings')) {
                $table->string('npl_ratings', 30)->default('3,4,5')->after('individual_top_n');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stage_configs', function (Blueprint $table) {
            $drop = array_filter(
                ['individual_top_n', 'npl_ratings'],
                fn ($c) => Schema::hasColumn('stage_configs', $c)
            );
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};