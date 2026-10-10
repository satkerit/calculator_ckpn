<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table) {
            $table->string('period_name')->nullable()->after('period_code');
            $table->unsignedTinyInteger('fiscal_month')->nullable()->after('period_name');
            $table->unsignedInteger('fiscal_year')->nullable()->after('fiscal_month');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_periods', function (Blueprint $table) {
            $table->dropColumn(['period_name', 'fiscal_month', 'fiscal_year']);
        });
    }
};
