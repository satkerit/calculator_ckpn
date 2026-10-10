<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop FK dari write_offs → financing (collateral FK already dropped in earlier migration)
        // ponytail: driver-agnostic FK check; replace with native introspection per-driver if ever needed
        try {
            Schema::table('write_offs', function (Blueprint $table) {
                $table->dropForeign(['financing_id']);
            });
        } catch (\Throwable) {
            // FK tidak ada — aman diabaikan.
        }

        // Tambah kolom ke snapshot (idempotent)
        Schema::table('snapshot', function (Blueprint $table) {
            if (!Schema::hasColumn('snapshot', 'usage_type')) {
                $table->string('usage_type')->nullable()->after('product_code');
            }
            if (!Schema::hasColumn('snapshot', 'tglwo')) {
                $table->string('tglwo', 8)->nullable()->after('usage_type'); // yyyymmdd format
            }
        });

        // Drop table financing
        Schema::dropIfExists('financing');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate financing table
        Schema::create('financing', function (Blueprint $table) {
            $table->string('loan_number')->primary();
            $table->string('cif_number')->nullable();
            $table->string('name')->nullable();
            $table->string('usage_code')->nullable();
            $table->date('tglwo')->nullable();
            $table->boolean('active_flag')->default(true);
            $table->string('classification')->nullable();
            $table->timestamps();
        });

        // Restore FK collateral → financing
        Schema::table('collateral', function (Blueprint $table) {
            $table->foreign('loan_number')
                ->references('loan_number')
                ->on('financing')
                ->cascadeOnDelete();
        });

        // Drop kolom dari snapshot
        Schema::table('snapshot', function (Blueprint $table) {
            $table->dropColumn(['usage_type', 'tglwo']);
        });
    }
};
