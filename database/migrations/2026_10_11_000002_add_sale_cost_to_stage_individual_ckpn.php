<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stage_individual_ckpn')) {
            Schema::table('stage_individual_ckpn', function (Blueprint $table) {
                if (! Schema::hasColumn('stage_individual_ckpn', 'liquidity_value')) {
                    $table->decimal('liquidity_value', 18, 2)->default(0)->after('nilai_tercatat');
                }
                if (! Schema::hasColumn('stage_individual_ckpn', 'sale_cost')) {
                    $table->decimal('sale_cost', 18, 2)->default(0)->after('liquidity_value');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stage_individual_ckpn') && Schema::hasColumn('stage_individual_ckpn', 'sale_cost')) {
            Schema::table('stage_individual_ckpn', function (Blueprint $table) {
                $table->dropColumn(['sale_cost', 'liquidity_value']);
            });
        }
    }
};
