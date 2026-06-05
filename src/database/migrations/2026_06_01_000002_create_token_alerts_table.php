<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()
                ->comment('Usuário monitorado; null = alerta global');
            $table->unsignedInteger('threshold_tokens');
            $table->enum('period', ['daily', 'weekly', 'monthly', 'total'])->default('monthly');
            $table->boolean('notify_user')->default(false);
            $table->boolean('notify_auditors')->default(true);
            $table->boolean('notify_admins')->default(false);
            $table->string('notify_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'period']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_alerts');
    }
};
