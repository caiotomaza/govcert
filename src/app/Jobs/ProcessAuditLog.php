<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Services\Audit\AuditAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAuditLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $auditLogId) {}

    public function handle(AuditAnalysisService $service): void
    {
        $log = AuditLog::find($this->auditLogId);

        if (! $log) {
            return;
        }

        $service->analyze($log);
    }

    /**
     * Chamado pelo Laravel quando o job esgota todas as tentativas com exceção não capturada.
     * Garante status terminal mesmo que handle() não tenha chegado a atualizar o banco.
     */
    public function failed(Throwable $exception): void
    {
        AuditLog::where('id', $this->auditLogId)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'failed',
                'error_reason' => mb_substr($exception->getMessage() ?: get_class($exception), 0, 1000),
            ]);

        rescue(fn () => Log::error('[GovCert] ProcessAuditLog: todas as tentativas esgotadas', [
            'audit_log_id' => $this->auditLogId,
            'error' => mb_substr($exception->getMessage() ?: get_class($exception), 0, 1000),
        ]));
    }
}
