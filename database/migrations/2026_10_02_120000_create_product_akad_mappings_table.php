<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_akad_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('product_code')->unique()->index();
            $table->string('product_name');
            $table->string('akad_code')->index(); // MURA, MJA, MUSY, IMBT
            $table->string('akad_name');
            $table->string('ead_rule_type')->default('MURABAHAH'); // MURABAHAH, MULTIJASA, MUSYARAKAH, IMBT
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->foreign('akad_code')->references('kode_akad')->on('akad_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_akad_mappings');
    }
};
