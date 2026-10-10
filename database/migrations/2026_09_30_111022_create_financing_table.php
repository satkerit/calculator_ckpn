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
        Schema::create('financing', function (Blueprint $table) {
            $table->string('loan_number')->primary()->unique();
            $table->string('cif_number');
            $table->string('name')->nullable();
            $table->date('tglwo')->nullable();
            $table->string('usage_code')->nullable();
            $table->boolean('active_flag')->default(true);
            $table->timestamps();
            
            $table->index('loan_number');
            $table->index('cif_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financing');
    }
};
