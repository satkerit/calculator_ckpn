<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_configs', function (Blueprint $table) {
            $table->decimal('ead_individual_threshold', 18, 2)->default(500000000)->after('segmentation_dims');
            $table->decimal('lgd_default', 5, 4)->default(0.5)->after('ead_individual_threshold');
            $table->decimal('lgd_er', 5, 4)->nullable()->after('lgd_default');
            $table->decimal('lgd_cs', 5, 4)->nullable()->after('lgd_er');
        });
    }

    public function down(): void
    {
        Schema::table('stage_configs', function (Blueprint $table) {
            $table->dropColumn(['segmentation_dims', 'individual_top_n', 'npl_ratings']);
        });
    }
};