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
            $table->dropForeign(['loan_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('snapshot', function (Blueprint $table) {
            $table->foreign('loan_number')
                ->references('loan_number')
                ->on('financing')
                ->cascadeOnDelete();
        });
    }
};
