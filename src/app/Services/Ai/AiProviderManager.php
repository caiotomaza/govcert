<?php

namespace App\Services\Ai;

use App\Models\AiProviderSetting;
use App\Services\Ai\Contracts\AiProviderClient;

class AiProviderManager
{
    /**
     * Retorna o cliente para a configuração de provedor ativa no banco.
     * Fallback: usa a GEMINI_API_KEY do .env para manter compatibilidade.
     */
    public static function activeClient(): AiProviderClient
    {
        $setting = AiProviderSetting::active();

        if ($setting && $setting->provider === 'gemini') {
            $key = $setting->decryptedApiKey() ?: (string) config('services.gemini.api_key', '');

            if ($key === '') {
                throw new \RuntimeException('API key do Gemini não configurada.');
            }

            return new GeminiClient(
                apiKey: $key,
                model: $setting->model ?: GeminiClient::DEFAULT_MODEL,
                timeoutSeconds: $setting->timeout_seconds ?: 30,
            );
        }

        if ($setting && $setting->decryptedApiKey()) {
            return self::buildClient($setting);
        }

        if ($setting) {
            throw new \RuntimeException('API key do provedor de IA ativo não configurada.');
        }

        $envKey = (string) config('services.gemini.api_key', '');

        if ($envKey === '') {
            throw new \RuntimeException('Nenhum provedor de IA ativo com API key configurada.');
        }

        // Fallback para variáveis de ambiente (compatibilidade com configuração anterior)
        return new GeminiClient(
            apiKey: $envKey,
            model: GeminiClient::DEFAULT_MODEL,
            timeoutSeconds: 30,
        );
    }

    /**
     * Constrói cliente a partir de uma configuração específica (para teste de API).
     */
    public static function buildClient(AiProviderSetting $setting): AiProviderClient
    {
        $key = $setting->decryptedApiKey() ?? '';
        $model = $setting->model ?: GeminiClient::DEFAULT_MODEL;
        $timeout = $setting->timeout_seconds;

        return match ($setting->provider) {
            'gemini' => new GeminiClient($key, $model, $timeout),
            'grok' => new OpenAiCompatibleClient(
                $key, $model,
                $setting->base_url ?: 'https://api.x.ai/v1',
                'Grok (xAI)',
                $timeout
            ),
            'deepseek' => new OpenAiCompatibleClient(
                $key, $model,
                $setting->base_url ?: 'https://api.deepseek.com/v1',
                'DeepSeek',
                $timeout
            ),
            default => new OpenAiCompatibleClient(
                $key, $model,
                $setting->base_url ?: '',
                'Customizado',
                $timeout
            ),
        };
    }
}
