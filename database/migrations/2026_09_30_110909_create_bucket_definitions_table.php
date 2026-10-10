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
        Schema::create('bucket_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('kode_bucket')->unique();
            $table->string('deskripsi_hari');
            $table->integer('hari_min');
            $table->integer('hari_max');
            $table->integer('urutan');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('hari_min');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bucket_definitions');
    }
};
