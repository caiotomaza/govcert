<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->unsignedInteger('input_tokens')->default(0)->after('output_text');
            $table->unsignedInteger('output_tokens')->default(0)->after('input_tokens');
            $table->unsignedInteger('total_tokens')->default(0)->after('output_tokens');
            $table->string('token_count_method', 30)->nullable()->after('total_tokens');

            $table->index('total_tokens');
            $table->index(['user_id', 'captured_at']);
            $table->index(['user_identifier', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['total_tokens']);
            $table->dropIndex(['user_id', 'captured_at']);
            $table->dropIndex(['user_identifier', 'captured_at']);
            $table->dropColumn(['input_tokens', 'output_tokens', 'total_tokens', 'token_count_method']);
        });
    }
};
