<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table 1: Min. Bucket – outstanding per bucket per periode
        Schema::create('pd_netflow_bucket_snapshots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_period_id');
            $table->string('group_key', 100)->index(); // office|akad|usage
            $table->unsignedTinyInteger('bucket_no'); // 1-14
            $table->date('periode_snapshot')->index();
            $table->decimal('outstanding_balance', 20, 2)->default(0);
            $table->unsignedInteger('account_count')->default(0);
            $table->timestamps();

            $table->unique(['assessment_period_id', 'group_key', 'bucket_no', 'periode_snapshot'], 'uniq_bucket_snapshot');
            $table->foreign('assessment_period_id')->references('id')->on('assessment_periods')->cascadeOnDelete();
        });

        // Table 2: Perpindahan Bucket – transition rate from→to per periode
        Schema::create('pd_netflow_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_period_id');
            $table->string('group_key', 100)->index();
            $table->unsignedTinyInteger('from_bucket'); // 1-13
            $table->unsignedTinyInteger('to_bucket');   // 2-14
            $table->date('periode')->index();
            $table->decimal('transition_rate', 8, 6)->default(0); // 0-1
            $table->unsignedInteger('flow_count')->default(0);
            $table->timestamps();

            $table->unique(['assessment_period_id', 'group_key', 'from_bucket', 'to_bucket', 'periode'], 'uniq_transition');
            $table->foreign('assessment_period_id')->references('id')->on('assessment_periods')->cascadeOnDelete();
        });

        // Table 3: Compound Flow to Loss – PD compound per source bucket
        Schema::create('pd_netflow_compound_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('assessment_period_id');
            $table->string('group_key', 100)->index();
            $table->unsignedTinyInteger('source_bucket'); // 1-13
            $table->date('periode')->index();
            $table->decimal('compound_pd', 8, 6)->default(0); // 0-1
            $table->json('flow_path')->nullable(); // trace: [1→2→3→...→14]
            $table->timestamps();

            $table->unique(['assessment_period_id', 'group_key', 'source_bucket', 'periode'], 'uniq_compound');
            $table->foreign('assessment_period_id')->references('id')->on('assessment_periods')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pd_netflow_compound_flows');
        Schema::dropIfExists('pd_netflow_transitions');
        Schema::dropIfExists('pd_netflow_bucket_snapshots');
    }
};
