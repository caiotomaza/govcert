<?php

namespace Tests\Feature;

use App\Jobs\ProcessAuditLog;
use App\Models\AiProviderSetting;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessPendingCommandTest extends TestCase
{
    use RefreshDatabase;

    private function configureGemini(): void
    {
        AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'api_key_encrypted' => encrypt('AIzaFakeKey'),
            'is_active' => true,
        ]);
    }

    private function fakeGemini(array $analysis): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($analysis)]]],
                ]],
            ], 200),
        ]);
    }

    /** Força updated_at no banco sem passar pelos timestamps do Eloquent. */
    private function setUpdatedAt(AuditLog $log, \DateTimeInterface $date): void
    {
        DB::table('audit_logs')->where('id', $log->id)->update([
            'updated_at' => $date->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_enfileira_logs_pending_antigos(): void
    {
        Queue::fake();

        // Log recente — NÃO deve ser enfileirado
        AuditLog::factory()->create(['status' => 'pending']);

        // Log antigo — DEVE ser enfileirado
        $old = AuditLog::factory()->create(['status' => 'pending']);
        $this->setUpdatedAt($old, now()->subMinutes(20));

        $this->artisan('audit:process-pending', [
            '--status' => 'pending',
            '--older-than' => '15',
            '--limit' => '10',
        ])->assertSuccessful();

        Queue::assertPushed(ProcessAuditLog::class, fn (ProcessAuditLog $job) => $job->auditLogId === $old->id);
        Queue::assertPushedTimes(ProcessAuditLog::class, 1);
    }

    public function test_enfileira_logs_in_analysis_travados(): void
    {
        Queue::fake();

        // in_analysis recente — NÃO deve ser recuperado
        AuditLog::factory()->create(['status' => 'in_analysis']);

        // in_analysis travado — DEVE ser recuperado
        $stuck = AuditLog::factory()->create(['status' => 'in_analysis']);
        $this->setUpdatedAt($stuck, now()->subMinutes(30));

        $this->artisan('audit:process-pending', [
            '--status' => 'in_analysis',
            '--older-than' => '15',
        ])->assertSuccessful();

        Queue::assertPushed(ProcessAuditLog::class, fn (ProcessAuditLog $job) => $job->auditLogId === $stuck->id);
        Queue::assertPushedTimes(ProcessAuditLog::class, 1);
    }

    public function test_enfileira_logs_failed_para_reprocessamento(): void
    {
        Queue::fake();

        $failed = AuditLog::factory()->failed()->create();
        $this->setUpdatedAt($failed, now()->subMinutes(20));

        $this->artisan('audit:process-pending', [
            '--status' => 'failed',
            '--older-than' => '15',
        ])->assertSuccessful();

        Queue::assertPushed(ProcessAuditLog::class, fn (ProcessAuditLog $job) => $job->auditLogId === $failed->id);
    }

    public function test_inline_processa_diretamente_sem_enfileirar(): void
    {
        Queue::fake();
        $this->configureGemini();
        $this->fakeGemini([
            'has_sensitive_data' => false,
            'risk_level' => 'low',
            'leak_type' => 'Nenhum',
            'justification' => 'Sem dados sensíveis.',
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);
        $this->setUpdatedAt($log, now()->subMinutes(20));

        $this->artisan('audit:process-pending', [
            '--status' => 'pending',
            '--older-than' => '15',
            '--inline' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame('completed', $log->fresh()->status);
    }

    public function test_dry_run_nao_modifica_banco_nem_enfileira(): void
    {
        Queue::fake();

        $log = AuditLog::factory()->create(['status' => 'pending']);
        $this->setUpdatedAt($log, now()->subMinutes(20));

        $this->artisan('audit:process-pending', [
            '--status' => 'pending',
            '--older-than' => '15',
            '--dry-run' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
        $this->assertSame('pending', $log->fresh()->status);
    }

    public function test_sem_logs_encerra_com_sucesso(): void
    {
        $this->artisan('audit:process-pending')
            ->expectsOutput('Nenhum log encontrado para processamento.')
            ->assertSuccessful();
    }

    public function test_status_invalido_retorna_failure(): void
    {
        $this->artisan('audit:process-pending', ['--status' => 'invalido'])
            ->assertFailed();
    }
}
