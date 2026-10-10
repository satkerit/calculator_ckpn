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
        Schema::create('collateral', function (Blueprint $table) {
            $table->string('loan_number');
            $table->string('collateral_code')->nullable();
            $table->integer('collateral_order');
            $table->string('collateral_type');
            $table->decimal('liquidity_value', 15, 2)->nullable();
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->date('valuation_date')->nullable();
            $table->boolean('active_flag')->default(true);
            $table->timestamps();
            
            $table->foreign('loan_number')
                ->references('loan_number')
                ->on('financing')
                ->cascadeOnDelete();
            $table->foreign('collateral_type')
                ->references('collateral_type')
                ->on('collateral_setup')
                ->restrictOnDelete();
            
            $table->unique(['loan_number', 'collateral_order']);
            $table->index('loan_number');
            $table->index('collateral_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collateral');
    }
};
