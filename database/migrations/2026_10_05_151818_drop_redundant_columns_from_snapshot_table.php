<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            if (Schema::hasColumn('snapshot', 'akad_code')) {
                $table->dropColumn('akad_code');
            }
            if (Schema::hasColumn('snapshot', 'usage_code')) {
                $table->dropColumn('usage_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            $table->string('akad_code')->nullable();
            $table->string('usage_code')->nullable();
        });
    }
};
