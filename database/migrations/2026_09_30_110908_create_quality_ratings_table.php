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
        Schema::create('quality_ratings', function (Blueprint $table) {
            $table->id();
            $table->string('kode_kualitas')->unique();
            $table->string('nama_kualitas');
            $table->integer('urutan');
            $table->string('status')->default('ACTIVE');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('urutan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quality_ratings');
    }
};
