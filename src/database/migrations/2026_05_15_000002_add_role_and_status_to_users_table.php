<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Inclui 'usuario' para compatibilidade com SQLite (testes).
            // No MySQL a migration 2026_06_01_000005 formaliza o ENUM.
            $table->enum('role', ['admin', 'auditor', 'usuario'])->default('auditor')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
