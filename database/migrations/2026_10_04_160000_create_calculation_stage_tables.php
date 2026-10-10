<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Klasifikasi Individu/Kolektif
        if (!Schema::hasTable('stage_classifications')) {
            Schema::create('stage_classifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('loan_number', 50);
                $table->string('office_code', 20)->nullable();
                $table->string('akad_code', 10)->nullable();
                $table->string('usage_code', 10)->nullable();
                $table->decimal('ead_amount', 18, 2)->default(0);
                $table->string('classification', 20); // INDIVIDUAL / COLLECTIVE
                $table->string('reason', 100)->nullable();
                $table->string('applied_rule', 100)->nullable();
                $table->timestamps();

                $table->index(['assessment_period_id', 'classification'], 'stage_class_idx');
                $table->index(['assessment_period_id', 'office_code', 'akad_code', 'usage_code'], 'stage_class_seg_idx');
            });
        }

        // 2. CKPN Individual
        if (!Schema::hasTable('stage_individual_ckpn')) {
            Schema::create('stage_individual_ckpn', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('loan_number', 50);
                $table->decimal('nilai_tercatat', 18, 2)->default(0);
                $table->decimal('estimated_recovery', 18, 2)->default(0);
                $table->decimal('ckpn_individual', 18, 2)->default(0);
                $table->string('recovery_method', 50)->nullable();
                $table->timestamps();

                $table->index(['assessment_period_id', 'loan_number'], 'stage_ind_ckpn_idx');
            });
        }

        // 3. Konfigurasi perhitungan per stage
        if (!Schema::hasTable('stage_configs')) {
            Schema::create('stage_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('pd_method', 20)->default('NET_FLOW');
                $table->string('lgd_method', 30)->default('WEIGHTED');
                $table->integer('historical_months')->default(24);
                $table->decimal('sale_cost_percentage', 5, 4)->default(0.02);
                $table->string('segmentation_dims', 50)->default('OFFICE|AKAD|USAGE');
                $table->timestamps();

                $table->unique('assessment_period_id');
            });
        }

        // 4. Hasil PD per grup segmentasi
        if (!Schema::hasTable('stage_pd_results')) {
            Schema::create('stage_pd_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('group_key', 100);
                $table->decimal('pd_calculated', 9, 6)->default(0);
                $table->string('method', 20)->nullable();
                $table->integer('periods_analyzed')->default(0);
                $table->json('detail')->nullable();
                $table->timestamps();

                $table->unique(['assessment_period_id', 'group_key'], 'stage_pd_unique');
            });
        }

        // 5. EAD per Bucket per Segmentasi — Tabel 1 kertas kerja (posisi penilaian)
        if (!Schema::hasTable('stage_ead_buckets')) {
            Schema::create('stage_ead_buckets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('group_key', 100);
                $table->tinyInteger('bucket_no');
                $table->string('hari_tunggakan', 30)->nullable();
                $table->decimal('ead', 18, 2)->default(0);
                $table->integer('member_count')->default(0);
                $table->timestamps();

                $table->index(['assessment_period_id', 'group_key', 'bucket_no'], 'stage_ead_bucket_idx');
            });
        }

        // 6. Snapshot historical terpisah per segmentasi
        if (!Schema::hasTable('stage_segmented_snapshots')) {
            Schema::create('stage_segmented_snapshots', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('group_key', 100);
                $table->string('periode_snapshot', 6);
                $table->string('loan_number', 50);
                $table->tinyInteger('bucket_no');
                $table->decimal('ead', 18, 2)->default(0);
                $table->timestamps();

                $table->index(['assessment_period_id', 'group_key', 'periode_snapshot'], 'stage_seg_snap_idx');
                $table->index(['assessment_period_id', 'group_key', 'loan_number'], 'stage_seg_loan_idx');
            });
        }

        // 7. Group Historical per Bucket per Periode — Tabel 1 historis
        if (!Schema::hasTable('stage_historical_buckets')) {
            Schema::create('stage_historical_buckets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('group_key', 100);
                $table->string('periode_snapshot', 6);
                $table->tinyInteger('bucket_no');
                $table->string('hari_tunggakan', 30)->nullable();
                $table->decimal('ead', 18, 2)->default(0);
                $table->integer('member_count')->default(0);
                $table->timestamps();

                $table->unique(['assessment_period_id', 'group_key', 'periode_snapshot', 'bucket_no'], 'hist_bucket_unique');
            });
        }

        // 8. Persentase Pergerakan Bucket — Tabel 2 kertas kerja
        if (!Schema::hasTable('stage_migration_rates')) {
            Schema::create('stage_migration_rates', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('group_key', 100);
                $table->string('periode_snapshot', 6);
                $table->tinyInteger('from_bucket');
                $table->tinyInteger('to_bucket');
                $table->decimal('rate', 9, 6)->default(0);
                $table->timestamps();

                $table->unique(['assessment_period_id', 'group_key', 'periode_snapshot', 'from_bucket', 'to_bucket'], 'mig_rate_unique');
            });
        }

        // 9. Compound Flow Loss — Tabel 3 kertas kerja
        if (!Schema::hasTable('stage_compound_flow')) {
            Schema::create('stage_compound_flow', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('group_key', 100);
                $table->tinyInteger('from_bucket');
                $table->string('periode_snapshot', 6);
                $table->decimal('compound_loss', 9, 6)->default(0);
                $table->timestamps();

                $table->index(['assessment_period_id', 'group_key', 'from_bucket'], 'comp_flow_idx');
            });
        }

        // 10. CKPN Kolektif per Segmentasi
        if (!Schema::hasTable('stage_collective_ckpn')) {
            Schema::create('stage_collective_ckpn', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();
                $table->string('group_key', 100);
                $table->string('office_code', 20)->nullable();
                $table->string('akad_code', 10)->nullable();
                $table->string('usage_code', 10)->nullable();
                $table->decimal('total_ead', 18, 2)->default(0);
                $table->decimal('pd', 9, 6)->default(0);
                $table->decimal('lgd', 9, 6)->default(0);
                $table->string('lgd_method', 30)->nullable();
                $table->decimal('ckpn_kolektif', 18, 2)->default(0);
                $table->integer('member_count')->default(0);
                $table->timestamps();

                $table->unique(['assessment_period_id', 'group_key'], 'coll_ckpn_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_collective_ckpn');
        Schema::dropIfExists('stage_compound_flow');
        Schema::dropIfExists('stage_migration_rates');
        Schema::dropIfExists('stage_historical_buckets');
        Schema::dropIfExists('stage_segmented_snapshots');
        Schema::dropIfExists('stage_ead_buckets');
        Schema::dropIfExists('stage_pd_results');
        Schema::dropIfExists('stage_configs');
        Schema::dropIfExists('stage_individual_ckpn');
        Schema::dropIfExists('stage_classifications');
    }
};
