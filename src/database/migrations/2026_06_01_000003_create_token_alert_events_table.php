<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('token_alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('token_alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('current_tokens');
            $table->unsignedInteger('threshold_tokens');
            $table->timestamp('triggered_at');
            $table->json('notified_targets')->nullable();
            $table->timestamps();

            $table->index(['token_alert_id', 'triggered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('token_alert_events');
    }
};
