<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SensitiveDataMasker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AuditLogDetailController extends Controller
{
    public function __construct(private readonly SensitiveDataMasker $masker) {}

    /**
     * Retorna os detalhes de um log com dados sensíveis mascarados.
     * O conteúdo original NUNCA é enviado ao frontend — nem para admin.
     *
     * error_reason: enviado somente para admin e sanitizado antes de exibir.
     */
    public function show(AuditLog $log): JsonResponse
    {
        $aiFindings = $log->gemini_raw_response;
        $isAdmin = Auth::user()?->isAdmin() ?? false;

        $tokenMethod = match ($log->token_count_method) {
            'api', 'gemini_api' => 'api',
            'estimated' => 'estimado',
            default => 'não informado',
        };

        return response()->json([
            'id' => $log->id,
            'user_identifier' => $log->user_identifier,
            'ai_agent_name' => $log->ai_agent_name,
            'url_source' => $log->url_source,
            'status' => $log->status,
            'risk_level' => $log->risk_level,
            'leak_type' => $log->leak_type,
            'gemini_justification' => $log->gemini_justification,
            // Motivo técnico: null para não-admin; string sanitizada para admin.
            'error_reason' => $isAdmin ? $this->sanitizeErrorReason($log->error_reason) : null,
            'captured_at' => $log->captured_at?->format('d/m/Y H:i:s'),
            // Conteúdo sempre mascarado — dados originais nunca saem do banco
            'input_masked' => $this->masker->mask($log->input_text ?? '', $aiFindings),
            'output_masked' => $this->masker->mask($log->output_text ?? '', $aiFindings),
            // Tokens apenas deste log
            'input_tokens' => $log->input_tokens > 0 ? $log->input_tokens : null,
            'output_tokens' => $log->output_tokens > 0 ? $log->output_tokens : null,
            'total_tokens' => $log->total_tokens > 0 ? $log->total_tokens : null,
            'token_method' => $log->total_tokens > 0 ? $tokenMethod : null,
        ]);
    }

    /**
     * Sanitiza o motivo de falha antes de exibir mesmo para admin.
     *
     * Os clientes de IA já removem a API key antes de armazenar (via sanitizeError()),
     * mas aplicamos uma segunda camada aqui para quaisquer padrões residuais.
     */
    private function sanitizeErrorReason(?string $reason): string
    {
        if (! $reason || trim($reason) === '') {
            return 'A análise falhou, mas nenhum motivo técnico foi registrado.';
        }

        $patterns = [
            '/AKIA[0-9A-Z]{16}/'                           => '[CHAVE AWS]',
            '/AIza[A-Za-z0-9_\-]{20,}/'                    => '[CHAVE GOOGLE]',
            '/\bsk-[A-Za-z0-9\-]{20,}\b/'                  => '[CHAVE API]',
            '/eyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+/' => '[JWT]',
            '/Bearer\s+[A-Za-z0-9\-._~+\/]{10,}=*/'        => 'Bearer [TOKEN]',
        ];

        $clean = $reason;
        foreach ($patterns as $pattern => $replacement) {
            $clean = preg_replace($pattern, $replacement, $clean) ?? $clean;
        }

        return mb_substr(trim($clean), 0, 500);
    }
}
