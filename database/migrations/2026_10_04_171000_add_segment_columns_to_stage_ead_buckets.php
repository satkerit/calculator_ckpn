<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_ead_buckets', function (Blueprint $table) {
            $table->string('office_code', 20)->nullable()->after('group_key');
            $table->string('akad_code', 10)->nullable()->after('office_code');
            $table->string('usage_code', 10)->nullable()->after('akad_code');
            $table->index(['assessment_period_id', 'office_code', 'akad_code', 'usage_code'], 'stage_ead_bucket_seg_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stage_ead_buckets', function (Blueprint $table) {
            $table->dropIndex('stage_ead_bucket_seg_idx');
            $table->dropColumn(['office_code', 'akad_code', 'usage_code']);
        });
    }
};