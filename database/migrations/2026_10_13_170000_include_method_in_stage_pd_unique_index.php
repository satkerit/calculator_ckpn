<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * stage_pd_unique hanya (assessment_period_id, group_key) — NET_FLOW dan
 * MIGRATION untuk grup sama pada periode sama saling menabrak (1062).
 * Unique key ikutkan kolom method agar kedua metode dapat tersimpan berdampingan.
 * Index lama dipakai FK assessment_period_id, maka FK di-drop dan dibuat ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_pd_results', function (Blueprint $table) {
            $table->dropForeign(['assessment_period_id']);
            $table->dropUnique('stage_pd_unique');
            $table->unique(['assessment_period_id', 'group_key', 'method'], 'stage_pd_unique');
            $table->foreign('assessment_period_id')->references('id')->on('assessment_periods')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stage_pd_results', function (Blueprint $table) {
            $table->dropForeign(['assessment_period_id']);
            $table->dropUnique('stage_pd_unique');
            $table->unique(['assessment_period_id', 'group_key'], 'stage_pd_unique');
            $table->foreign('assessment_period_id')->references('id')->on('assessment_periods')->cascadeOnDelete();
        });
    }
};
