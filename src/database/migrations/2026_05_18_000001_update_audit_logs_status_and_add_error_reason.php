<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('error_reason')->nullable()->after('status');
        });

        DB::table('audit_logs')
            ->where('status', 'processing')
            ->update(['status' => 'in_analysis']);

        // MODIFY COLUMN é exclusivo do MySQL; SQLite não precisa pois não
        // impõe tipos ENUM — o valor já é controlado na camada de aplicação.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE audit_logs
                 MODIFY COLUMN status
                 ENUM('pending','in_analysis','completed','failed')
                 NOT NULL DEFAULT 'pending'"
            );
        }
    }

    public function down(): void
    {
        DB::table('audit_logs')
            ->where('status', 'in_analysis')
            ->update(['status' => 'processing']);

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE audit_logs
                 MODIFY COLUMN status
                 ENUM('pending','processing','completed','failed')
                 NOT NULL DEFAULT 'pending'"
            );
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('error_reason');
        });
    }
};
