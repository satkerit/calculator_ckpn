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
            $table->integer('days_past_due')->default(0)->after('principal_arrears');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            $table->dropColumn('days_past_due');
        });
    }
};
