<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Adiciona 'usuario' ao ENUM no MySQL.
        // No SQLite (testes) o enum vira CHECK constraint — como a migration de
        // criação já define a coluna como string com CHECK em sqlite, precisamos
        // recriar a constraint. O padrão adotado é idêntico ao que foi feito para
        // 'in_analysis' na migration 2026_05_18: condicional por driver.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','auditor','usuario') NOT NULL DEFAULT 'auditor'");
        }
        // SQLite já aceita qualquer string — a validação fica na camada de aplicação.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Move registros 'usuario' para 'auditor' antes de remover o valor
            DB::table('users')->where('role', 'usuario')->update(['role' => 'auditor']);
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin','auditor') NOT NULL DEFAULT 'auditor'");
        }
    }
};
