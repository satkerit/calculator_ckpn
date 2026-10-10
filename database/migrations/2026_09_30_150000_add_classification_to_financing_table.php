<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financing', function (Blueprint $table) {
            $table->enum('classification', ['INDIVIDUAL', 'COLLECTIVE'])->nullable()->after('active_flag');
            $table->index('classification');
        });
    }

    public function down(): void
    {
        Schema::table('financing', function (Blueprint $table) {
            $table->dropIndex(['classification']);
            $table->dropColumn('classification');
        });
    }
};
