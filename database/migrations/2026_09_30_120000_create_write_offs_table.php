<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('write_offs', function (Blueprint $table) {
            $table->id();
            $table->string('financing_id');
            $table->date('writeoff_date');
            $table->decimal('amount', 16, 2);
            $table->decimal('recovery_rate', 5, 4)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign('financing_id')->references('loan_number')->on('financing')->onDelete('cascade');
            $table->index('financing_id');
            $table->index('writeoff_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('write_offs');
    }
};
