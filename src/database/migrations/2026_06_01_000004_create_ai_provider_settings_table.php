<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('model')->nullable();
            $table->string('base_url')->nullable();
            $table->text('api_key_encrypted')->nullable()
                ->comment('API key criptografada via encrypt(). Nunca em texto puro.');
            $table->unsignedInteger('timeout_seconds')->default(30);
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable();
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_settings');
    }
};
