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
        Schema::create('snapshot', function (Blueprint $table) {
            $table->string('loan_number');
            $table->string('product_code')->nullable();
            $table->string('office_code');
            $table->date('effective_date')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->decimal('principal_arrears', 15, 2)->default(0);
            $table->string('financing_contract_code')->nullable();
            $table->boolean('active_flag')->default(true);
            $table->boolean('write_off_flag')->default(false);
            $table->decimal('ppka_value', 15, 2)->nullable();
            $table->string('periode_snapshot');
            $table->timestamps();
            
            $table->foreign('loan_number')
                ->references('loan_number')
                ->on('financing')
                ->cascadeOnDelete();
            $table->foreign('office_code')
                ->references('office_code')
                ->on('offices')
                ->restrictOnDelete();
            
            $table->index('loan_number');
            $table->index('office_code');
            $table->index('periode_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snapshot');
    }
};
