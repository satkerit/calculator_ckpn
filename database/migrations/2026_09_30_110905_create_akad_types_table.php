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
        Schema::create('akad_types', function (Blueprint $table) {
            $table->id();
            $table->string('kode_akad')->unique();
            $table->string('nama_akad');
            $table->string('kategori');
            $table->string('ead_rule_type');
            $table->boolean('requires_due_date')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index('kode_akad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('akad_types');
    }
};
