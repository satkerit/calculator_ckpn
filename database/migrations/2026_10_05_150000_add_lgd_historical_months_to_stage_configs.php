<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stage_configs') && ! Schema::hasColumn('stage_configs', 'lgd_historical_months')) {
            Schema::table('stage_configs', function (Blueprint $table) {
                $table->integer('lgd_historical_months')->default(60)->after('historical_months');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stage_configs') && Schema::hasColumn('stage_configs', 'lgd_historical_months')) {
            Schema::table('stage_configs', function (Blueprint $table) {
                $table->dropColumn('lgd_historical_months');
            });
        }
    }
};
