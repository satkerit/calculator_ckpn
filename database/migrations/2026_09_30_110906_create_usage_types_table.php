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
        Schema::create('usage_types', function (Blueprint $table) {
            $table->id();
            $table->string('kode_jenis_penggunaan')->unique();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('kode_jenis_penggunaan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usage_types');
    }
};
