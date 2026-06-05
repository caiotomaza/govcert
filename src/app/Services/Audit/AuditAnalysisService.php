<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Services\Ai\AiProviderManager;
use App\Services\TokenAlertService;
use App\Services\TokenCounter;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditAnalysisService
{
    public function __construct(
        private readonly TokenCounter $counter,
        private readonly TokenAlertService $alerts,
    ) {}

    /**
     * Analisa um AuditLog usando o provedor de IA ativo.
     *
     * A guarda atômica garante que `completed` nunca é reprocessado, mesmo sob
     * concorrência (dois workers pegando o mesmo job). O status no banco é sempre
     * a fonte de verdade — o serviço nunca relança exceções.
     */
    public function analyze(AuditLog $log): void
    {
        // Transição atômica: `completed` é terminal — nunca reprocessar.
        // Para pending, in_analysis e failed a transição é permitida.
        $affected = AuditLog::where('id', $log->id)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'in_analysis',
                'error_reason' => null,
            ]);

        if ($affected === 0) {
            return;
        }

        $log->refresh();

        $tokens = $this->counter->countPair($log->input_text ?? '', $log->output_text ?? '');

        $log->update([
            'input_tokens' => $tokens['input'],
            'output_tokens' => $tokens['output'],
            'total_tokens' => $tokens['total'],
            'token_count_method' => $tokens['method'],
        ]);

        try {
            $client = AiProviderManager::activeClient();
            $result = $client->analyzeAuditLog($log);

            $refined = $this->counter->fromGeminiMetadata(
                $result['usageMetadata'] ?? null,
                $log->input_text ?? '',
                $log->output_text ?? '',
            );

            $log->update([
                'status' => 'completed',
                'has_sensitive_data' => $result['has_sensitive_data'],
                'risk_level' => $result['risk_level'],
                'leak_type' => $result['leak_type'] ?? null,
                'gemini_justification' => $result['justification'],
                'gemini_raw_response' => $result,
                'processed_at' => now(),
                'input_tokens' => $refined['input'],
                'output_tokens' => $refined['output'],
                'total_tokens' => $refined['total'],
                'token_count_method' => $refined['method'],
            ]);

            // Verifica alertas de consumo de tokens após cada análise bem-sucedida.
            // Usa rescue() para que uma falha nos alertas nunca afete a auditoria.
            rescue(fn () => $this->alerts->checkAll());
        } catch (Throwable $e) {
            $reason = mb_substr($e->getMessage() ?: get_class($e), 0, 1000);

            $log->update([
                'status' => 'failed',
                'error_reason' => $reason,
                'processed_at' => now(),
            ]);

            rescue(fn () => Log::error('[GovCert] AuditAnalysisService: análise falhou', [
                'audit_log_id' => $log->id,
                'error' => $reason,
            ]));
        }
    }
}
