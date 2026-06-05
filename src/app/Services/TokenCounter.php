<?php

namespace App\Services;

class TokenCounter
{
    /**
     * Estima a quantidade de tokens de um texto.
     * Heurística: 1 token ≈ 4 caracteres (padrão OpenAI/Gemini para PT-BR).
     */
    public function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }

    /**
     * Estima tokens para um par input/output e retorna breakdown completo.
     *
     * @return array{input: int, output: int, total: int, method: string}
     */
    public function countPair(string $input, string $output): array
    {
        $inputTokens = $this->estimate($input);
        $outputTokens = $this->estimate($output);

        return [
            'input' => $inputTokens,
            'output' => $outputTokens,
            'total' => $inputTokens + $outputTokens,
            'method' => 'estimated',
        ];
    }

    /**
     * Usa os metadados de uso retornados pela API do Gemini quando disponíveis,
     * com fallback para estimativa local.
     *
     * @param  array<string, mixed>|null  $geminiUsageMetadata  Campo `usageMetadata` da resposta Gemini
     * @return array{input: int, output: int, total: int, method: string}
     */
    public function fromGeminiMetadata(?array $geminiUsageMetadata, string $input, string $output): array
    {
        if (
            isset($geminiUsageMetadata['promptTokenCount'], $geminiUsageMetadata['candidatesTokenCount'])
            && is_int($geminiUsageMetadata['promptTokenCount'])
            && is_int($geminiUsageMetadata['candidatesTokenCount'])
        ) {
            $inputTokens = $geminiUsageMetadata['promptTokenCount'];
            $outputTokens = $geminiUsageMetadata['candidatesTokenCount'];
            $totalTokens = isset($geminiUsageMetadata['totalTokenCount']) && is_int($geminiUsageMetadata['totalTokenCount'])
                ? $geminiUsageMetadata['totalTokenCount']
                : $inputTokens + $outputTokens;

            return [
                'input' => $inputTokens,
                'output' => $outputTokens,
                'total' => $totalTokens,
                'method' => 'api',
            ];
        }

        return $this->countPair($input, $output);
    }
}
