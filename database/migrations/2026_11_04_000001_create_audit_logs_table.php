<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit trail (AGENTS.md #14). Sebelumnya `BaseCalculationController::audit()`
     * selalu no-op karena tabel ini belum pernah dibuat.
     */
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Tanpa FK: jejak audit harus tetap ada walau user dihapus.
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('agent_name', 100);
            $table->string('action', 50);
            $table->string('module', 100)->nullable();
            $table->string('record_type', 100);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['record_type', 'record_id', 'action', 'created_at'], 'audit_logs_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
