<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiProviderSetting extends Model
{
    use HasFactory;

    protected $table = 'ai_provider_settings';

    protected $fillable = [
        'provider',
        'model',
        'base_url',
        'api_key_encrypted',
        'timeout_seconds',
        'is_active',
        'last_tested_at',
        'last_test_status',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_tested_at' => 'datetime',
        'timeout_seconds' => 'integer',
    ];

    /** Chave nunca é retornada em serialização. */
    protected $hidden = ['api_key_encrypted'];

    public function getProviderLabelAttribute(): string
    {
        return match ($this->provider) {
            'gemini' => 'Google Gemini',
            'grok' => 'Grok (xAI)',
            'deepseek' => 'DeepSeek',
            'custom' => 'Customizado (OpenAI-compatível)',
            default => ucfirst($this->provider),
        };
    }

    /** Retorna chave descriptografada apenas internamente — nunca expor ao frontend. */
    public function decryptedApiKey(): ?string
    {
        if (! $this->api_key_encrypted) {
            return null;
        }

        try {
            return decrypt($this->api_key_encrypted);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Versão mascarada para exibição na UI: `AIza****wxyz`. */
    public function maskedApiKey(): string
    {
        $key = $this->decryptedApiKey();

        if (! $key) {
            return '(não configurada)';
        }

        $len = mb_strlen($key);

        if ($len <= 8) {
            return str_repeat('*', $len);
        }

        return mb_substr($key, 0, 4).'****'.mb_substr($key, -4);
    }

    /** Retorna a configuração ativa ou null. */
    public static function active(): ?self
    {
        return static::where('is_active', true)->latest()->first();
    }

    /** Garante que apenas um registro fique ativo por vez. */
    public function activate(): void
    {
        static::where('is_active', true)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }
}
