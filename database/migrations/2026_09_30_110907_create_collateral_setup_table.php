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
        Schema::create('collateral_setup', function (Blueprint $table) {
            $table->string('collateral_type')->unique()->primary();
            $table->string('collateral_ket');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('collateral_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral_setup');
    }
};
