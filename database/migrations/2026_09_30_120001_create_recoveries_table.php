<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recoveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('write_off_id');
            $table->date('recovery_date');
            $table->decimal('amount', 16, 2);
            $table->string('recovery_source')->nullable();
            $table->timestamps();

            $table->foreign('write_off_id')->references('id')->on('write_offs')->onDelete('cascade');
            $table->index('write_off_id');
            $table->index('recovery_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recoveries');
    }
};
