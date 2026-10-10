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
            $table->string('quality_rating', 5)->nullable()->after('days_past_due');
            $table->index('quality_rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            $table->dropIndex(['quality_rating']);
            $table->dropColumn('quality_rating');
        });
    }
};
