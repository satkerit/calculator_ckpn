<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stage_lgd_results')) {
            return;
        }

        Schema::create('stage_lgd_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
            $table->string('group_key', 120);
            $table->string('combo_segmentation_key', 50)->nullable();
            $table->string('office_code', 20)->nullable();
            $table->string('akad_code', 10)->nullable();
            $table->string('usage_code', 10)->nullable();

            // ER | CS | WEIGHTED
            $table->string('method', 20);

            // Expected Recovery inputs
            $table->decimal('recovery_rate', 9, 6)->nullable();
            $table->decimal('total_write_off', 18, 2)->nullable();
            $table->decimal('total_recovery', 18, 2)->nullable();
            $table->integer('years_analyzed')->nullable();

            // Collateral Shortfall inputs
            $table->decimal('total_exposure_wo', 18, 2)->nullable();
            $table->decimal('total_shortfall', 18, 2)->nullable();
            $table->decimal('collateral_coverage', 9, 6)->nullable();
            $table->integer('account_count')->nullable();

            $table->decimal('lgd_calculated', 9, 6)->default(0);
            $table->json('detail')->nullable();
            $table->timestamps();

            $table->index(['assessment_period_id', 'method'], 'stage_lgd_period_method_idx');
            $table->index(['assessment_period_id', 'group_key'], 'stage_lgd_period_group_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_lgd_results');
    }
};
