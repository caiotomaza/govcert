<?php

namespace App\Services\Ai;

use App\Models\AuditLog;
use App\Services\Ai\Contracts\AiProviderClient;
use Illuminate\Support\Facades\Http;

/**
 * Cliente genérico compatível com a API OpenAI (chat/completions).
 * Usado para Grok, DeepSeek e provedores customizados.
 */
class OpenAiCompatibleClient implements AiProviderClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly string $providerLabel,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function testConnection(): AiProviderTestResult
    {
        $start = microtime(true);
        $status = 0;
        $error = null;

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withToken($this->apiKey)
                ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                    'model' => $this->model,
                    'messages' => [['role' => 'user', 'content' => 'Responda apenas: OK GovCert']],
                    'max_tokens' => 20,
                ]);

            $status = $response->status();
            $elapsed = (microtime(true) - $start) * 1000;
            $snippet = mb_substr((string) ($response->json('choices.0.message.content') ?? ''), 0, 120);

            if ($response->failed()) {
                $error = $this->sanitizeError($response->body());

                return new AiProviderTestResult(false, $this->providerLabel, $this->model, $status, $elapsed, $snippet, $error, now()->toIso8601String());
            }

            return new AiProviderTestResult(true, $this->providerLabel, $this->model, $status, $elapsed, $snippet, null, now()->toIso8601String());
        } catch (\Throwable $e) {
            $elapsed = (microtime(true) - $start) * 1000;

            return new AiProviderTestResult(false, $this->providerLabel, $this->model, 0, $elapsed, '', $this->sanitizeError($e->getMessage()), now()->toIso8601String());
        }
    }

    public function analyzeAuditLog(AuditLog $auditLog): array
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->withToken($this->apiKey)
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user',   'content' => $this->buildPrompt($auditLog)],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.1,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException("{$this->providerLabel} API error: ".$this->sanitizeError($response->body()));
        }

        $content = $response->json('choices.0.message.content');

        if (! $content) {
            throw new \RuntimeException("Empty response from {$this->providerLabel}");
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON from {$this->providerLabel}");
        }

        return $this->normalizeAnalysis($parsed);
    }

    /** @param array<string, mixed> $parsed */
    private function normalizeAnalysis(array $parsed): array
    {
        $riskLevel = strtolower((string) ($parsed['risk_level'] ?? 'low'));

        if (! in_array($riskLevel, ['low', 'medium', 'high', 'critical'], true)) {
            $riskLevel = 'low';
        }

        return [
            ...$parsed,
            'has_sensitive_data' => (bool) ($parsed['has_sensitive_data'] ?? false),
            'risk_level' => $riskLevel,
            'leak_type' => (string) ($parsed['leak_type'] ?? 'Nenhum'),
            'justification' => (string) ($parsed['justification'] ?? 'Análise concluída sem justificativa detalhada.'),
        ];
    }

    private function buildPrompt(AuditLog $auditLog): string
    {
        return sprintf(
            "URL de origem: %s\n\nINPUT DO USUÁRIO:\n%s\n\nOUTPUT DO SISTEMA DE IA:\n%s",
            $auditLog->url_source,
            $auditLog->input_text,
            $auditLog->output_text
        );
    }

    private function systemPrompt(): string
    {
        return 'Você é um sistema de auditoria pública de IA. Analise o INPUT e OUTPUT e retorne EXCLUSIVAMENTE um JSON: {"has_sensitive_data":boolean,"risk_level":"low|medium|high|critical","leak_type":"Dados Pessoais|Credenciais|Código Fonte|Nenhum","justification":"string","detected_categories":[],"lgpd_articles":[],"recommendations":[]}';
    }

    private function sanitizeError(string $message): string
    {
        return str_replace($this->apiKey, '***', mb_substr($message, 0, 500));
    }
}
