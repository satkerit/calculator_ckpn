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
        Schema::create('assessment_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_code')->unique();
            $table->date('assessment_date');
            $table->enum('status', ['DRAFT', 'VALIDATING', 'CALCULATING', 'REVIEW', 'APPROVED', 'LOCKED', 'CANCELLED'])->default('DRAFT');
            $table->string('source_version')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index('assessment_date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_periods');
    }
};
