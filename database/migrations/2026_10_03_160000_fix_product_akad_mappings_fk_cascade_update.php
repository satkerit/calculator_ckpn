<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_akad_mappings', function (Blueprint $table) {
            $table->dropForeign(['akad_code']);
            $table->foreign('akad_code')
                ->references('kode_akad')
                ->on('akad_types')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('product_akad_mappings', function (Blueprint $table) {
            $table->dropForeign(['akad_code']);
            $table->foreign('akad_code')
                ->references('kode_akad')
                ->on('akad_types')
                ->cascadeOnDelete();
        });
    }
};
