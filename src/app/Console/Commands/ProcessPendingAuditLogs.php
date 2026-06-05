<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAuditLog;
use App\Models\AuditLog;
use App\Services\Audit\AuditAnalysisService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessPendingAuditLogs extends Command
{
    protected $signature = 'audit:process-pending
        {--status=pending,in_analysis : Status a processar (pending, in_analysis, failed)}
        {--older-than=15 : Processar somente logs não atualizados há mais de N minutos (evita colisão com jobs ativos)}
        {--limit=50 : Máximo de logs por execução}
        {--inline : Processar diretamente em vez de enfileirar (síncrono, para recuperação imediata)}
        {--dry-run : Listar os logs que seriam processados sem executar nada}';

    protected $description = 'Localiza logs de auditoria pendentes ou travados e (re)enfileira para análise de IA';

    public function handle(AuditAnalysisService $service): int
    {
        $statuses = collect(explode(',', (string) $this->option('status')))
            ->map(fn (string $s) => trim($s))
            ->filter(fn (string $s) => in_array($s, ['pending', 'in_analysis', 'failed'], true))
            ->values();

        if ($statuses->isEmpty()) {
            $this->error('Nenhum status válido. Use: pending, in_analysis, failed.');

            return self::FAILURE;
        }

        $olderThan = max(1, (int) $this->option('older-than'));
        $limit = max(1, (int) $this->option('limit'));
        $inline = (bool) $this->option('inline');
        $dryRun = (bool) $this->option('dry-run');

        // Filtra somente logs que não foram atualizados nos últimos N minutos.
        // Para pending: protege contra jobs que acabaram de ser despachados.
        // Para in_analysis: identifica workers que crasharam sem finalizar.
        $cutoff = Carbon::now()->subMinutes($olderThan);

        $logs = AuditLog::whereIn('status', $statuses->all())
            ->where('updated_at', '<', $cutoff)
            ->oldest('updated_at')
            ->limit($limit)
            ->get();

        if ($logs->isEmpty()) {
            $this->info('Nenhum log encontrado para processamento.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Encontrados %d log(s) com status [%s] não atualizados há >%dmin.',
            $logs->count(),
            $statuses->implode(', '),
            $olderThan,
        ));

        if ($dryRun) {
            $this->table(
                ['ID', 'Status', 'Atualizado há', 'Usuário'],
                $logs->map(fn ($l) => [
                    $l->id,
                    $l->status,
                    $l->updated_at->diffForHumans(),
                    $l->user_identifier,
                ])->all()
            );

            return self::SUCCESS;
        }

        $queued = $succeeded = $failed = 0;

        foreach ($logs as $log) {
            if ($inline) {
                $service->analyze($log);
                $log->refresh();

                if ($log->status === 'completed') {
                    $succeeded++;
                    $this->line("  ✓ Log #{$log->id} → completed");
                } else {
                    $failed++;
                    $this->warn("  ✗ Log #{$log->id} → {$log->status}: {$log->error_reason}");
                }
            } else {
                ProcessAuditLog::dispatch($log->id)->onQueue('gemini');
                $queued++;
                $this->line("  → Log #{$log->id} despachado");
            }
        }

        if ($inline) {
            $this->info("Concluído: {$succeeded} sucesso(s), {$failed} falha(s).");
        } else {
            $this->info("{$queued} log(s) enviado(s) para a fila 'gemini'.");
        }

        return self::SUCCESS;
    }
}
