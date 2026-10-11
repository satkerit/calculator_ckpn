<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stage_calculation_marks')) {
            return;
        }

        Schema::create('stage_calculation_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_period_id')->constrained('assessment_periods')->cascadeOnDelete();

            // Jenis hasil yang ditandai: PD | LGD | CKPN.
            $table->string('result_type', 20);

            // sha1 fingerprint konfigurasi yang aktif saat kalkulasi terakhir.
            // Bila config berubah, fingerprint berbeda → hasil dianggap TIDAK VALID.
            $table->string('config_fingerprint', 40);

            $table->timestamps();

            $table->unique(['assessment_period_id', 'result_type'], 'stage_calc_marks_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_calculation_marks');
    }
};
