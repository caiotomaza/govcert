<?php

namespace App\Services\Ai;

use App\Models\AuditLog;
use App\Services\Ai\Contracts\AiProviderClient;
use Illuminate\Support\Facades\Http;

class GeminiClient implements AiProviderClient
{
    public const DEFAULT_MODEL = 'gemini-2.5-flash';

    private const BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private readonly string $apiKey,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly int $timeoutSeconds = 30,
    ) {}

    public function testConnection(): AiProviderTestResult
    {
        $start = microtime(true);
        $status = 0;
        $snippet = '';
        $error = null;

        try {
            if ($this->apiKey === '') {
                return new AiProviderTestResult(false, 'Gemini', $this->model, 0, 0, '', 'API key do Gemini não configurada.', now()->toIso8601String());
            }

            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpointUrl(), [
                    'contents' => [['parts' => [['text' => 'Responda apenas: OK GovCert']]]],
                    'generationConfig' => ['temperature' => 0, 'maxOutputTokens' => 20],
                ]);

            $status = $response->status();
            $elapsed = (microtime(true) - $start) * 1000;
            $content = $response->json('candidates.0.content.parts.0.text', '');
            $snippet = mb_substr((string) $content, 0, 120);

            if ($response->failed()) {
                $error = $this->sanitizeError($response->body());

                return new AiProviderTestResult(false, 'Gemini', $this->model, $status, $elapsed, $snippet, $error, now()->toIso8601String());
            }

            return new AiProviderTestResult(true, 'Gemini', $this->model, $status, $elapsed, $snippet, null, now()->toIso8601String());
        } catch (\Throwable $e) {
            $elapsed = (microtime(true) - $start) * 1000;
            $error = $this->sanitizeError($e->getMessage());

            return new AiProviderTestResult(false, 'Gemini', $this->model, $status, $elapsed, '', $error, now()->toIso8601String());
        }
    }

    public function analyzeAuditLog(AuditLog $auditLog): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('API key do Gemini não configurada.');
        }

        try {
            $response = Http::timeout($this->timeoutSeconds)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->endpointUrl(), [
                    'system_instruction' => ['parts' => [['text' => $this->systemPrompt()]]],
                    'contents' => [['parts' => [['text' => $this->buildPrompt($auditLog)]]]],
                    'generationConfig' => ['responseMimeType' => 'application/json', 'temperature' => 0.1],
                ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Gemini API request failed: '.$this->sanitizeError($e->getMessage()), 0, $e);
        }

        if ($response->failed()) {
            throw new \RuntimeException('Gemini API error: '.$this->sanitizeError($response->body()));
        }

        $content = $response->json('candidates.0.content.parts.0.text');

        if (! $content) {
            throw new \RuntimeException('Empty response from Gemini API');
        }

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from Gemini');
        }

        // Captura metadados de tokens quando disponíveis
        $parsed['usageMetadata'] = $response->json('usageMetadata');

        return $this->normalizeAnalysis($parsed);
    }

    private function endpointUrl(): string
    {
        return self::BASE_URL."/{$this->model}:generateContent?key={$this->apiKey}";
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
        return <<<'PROMPT'
Você é um sistema especializado em Auditoria, Certificação e Governança Pública de IA.
Analise o INPUT e OUTPUT e retorne EXCLUSIVAMENTE um JSON válido, sem markdown:

{
  "has_sensitive_data": true,
  "risk_level": "critical",
  "leak_type": "Dados Pessoais",
  "justification": "Foram encontrados dados pessoais no texto analisado.",
  "sensitive_findings": [
    {
      "type": "CPF",
      "field": "input_text",
      "description": "CPF identificado no prompt do usuário"
    }
  ]
}
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @return array<string, mixed>
     */
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
            'sensitive_findings' => is_array($parsed['sensitive_findings'] ?? null)
                ? $parsed['sensitive_findings']
                : [],
        ];
    }

    /** Remove possível API key do texto de erro antes de propagar. */
    private function sanitizeError(string $message): string
    {
        if ($this->apiKey === '') {
            return mb_substr($message, 0, 500);
        }

        return str_replace($this->apiKey, '***', mb_substr($message, 0, 500));
    }
}
