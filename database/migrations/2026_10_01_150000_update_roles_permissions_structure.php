<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('permissions', 'name')) {
                    $table->string('name', 100)->unique()->after('id');
                }
                if (!Schema::hasColumn('permissions', 'display_name')) {
                    $table->string('display_name', 100)->after('name');
                }
            });
        }

        if (!Schema::hasTable('role_permissions')) {
            Schema::create('role_permissions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('role_id')->constrained()->cascadeOnDelete();
                $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                
                $table->unique(['role_id', 'permission_id']);
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('role_id')->nullable()->after('password')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['role_id']);
                $table->dropColumn('role_id');
            });
        }

        Schema::dropIfExists('role_permissions');

        if (Schema::hasTable('permissions') && Schema::hasColumn('permissions', 'name')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropUnique(['name']);
                $table->dropColumn(['name', 'display_name', 'module']);
            });
        }
    }
};
