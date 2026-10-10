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
            $table->tinyInteger('bucket_no')->nullable()->after('write_off_flag')
                ->comment('Kriteria bucket dari snapshot: 0-hari, 1-30, 31-60, ..., >360+WO');
        });
    }

    public function down(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            $table->dropColumn('bucket_no');
        });
    }
};
