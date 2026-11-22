<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stage_configs', function (Blueprint $table) {
            if (! Schema::hasColumn('stage_configs', 'individual_include_kol5')) {
                $table->boolean('individual_include_kol5')
                    ->default(true)
                    ->after('npl_ratings');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stage_configs', function (Blueprint $table) {
            if (Schema::hasColumn('stage_configs', 'individual_include_kol5')) {
                $table->dropColumn('individual_include_kol5');
            }
        });
    }
};
