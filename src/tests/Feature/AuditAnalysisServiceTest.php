<?php

namespace Tests\Feature;

use App\Models\AiProviderSetting;
use App\Models\AuditLog;
use App\Services\Audit\AuditAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AuditAnalysisService
    {
        return app(AuditAnalysisService::class);
    }

    private function configureGemini(string $model = 'gemini-2.5-flash'): void
    {
        AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => $model,
            'api_key_encrypted' => encrypt('AIzaFakeKey'),
            'is_active' => true,
        ]);
    }

    private function fakeGemini(array $analysis, int $status = 200): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($analysis)]]],
                ]],
            ], $status),
        ]);
    }

    public function test_processa_log_pendente_e_marca_completed(): void
    {
        $this->configureGemini();
        $this->fakeGemini([
            'has_sensitive_data' => true,
            'risk_level' => 'high',
            'leak_type' => 'Dados Pessoais',
            'justification' => 'CPF detectado no input.',
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        $this->service()->analyze($log);

        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertTrue($log->has_sensitive_data);
        $this->assertSame('high', $log->risk_level);
        $this->assertSame('CPF detectado no input.', $log->gemini_justification);
        $this->assertNotNull($log->processed_at);
        $this->assertGreaterThan(0, $log->total_tokens);
    }

    public function test_recupera_log_travado_em_in_analysis(): void
    {
        $this->configureGemini();
        $this->fakeGemini([
            'has_sensitive_data' => false,
            'risk_level' => 'low',
            'leak_type' => 'Nenhum',
            'justification' => 'Sem dados sensíveis.',
        ]);

        $log = AuditLog::factory()->create(['status' => 'in_analysis']);

        $this->service()->analyze($log);

        $this->assertSame('completed', $log->fresh()->status);
    }

    public function test_reprocessa_log_failed(): void
    {
        $this->configureGemini();
        $this->fakeGemini([
            'has_sensitive_data' => false,
            'risk_level' => 'low',
            'leak_type' => 'Nenhum',
            'justification' => 'Sem dados sensíveis.',
        ]);

        $log = AuditLog::factory()->failed()->create();

        $this->service()->analyze($log);

        $this->assertSame('completed', $log->fresh()->status);
    }

    public function test_nao_reprocessa_log_completed(): void
    {
        Http::fake();

        $log = AuditLog::factory()->completed('low', false)->create();

        $this->service()->analyze($log);

        Http::assertNothingSent();
        $this->assertSame('completed', $log->fresh()->status);
    }

    public function test_marca_failed_quando_api_retorna_erro_http(): void
    {
        $this->configureGemini();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('Erro interno', 500),
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        $this->service()->analyze($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertNotNull($log->error_reason);
        $this->assertNotNull($log->processed_at);
    }

    public function test_marca_failed_sem_api_key_configurada(): void
    {
        Http::fake();
        config(['services.gemini.api_key' => null]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        $this->service()->analyze($log);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('API key', $log->error_reason);
        Http::assertNothingSent();
    }

    public function test_normaliza_risk_level_retornado_em_maiusculo(): void
    {
        $this->configureGemini();
        $this->fakeGemini([
            'has_sensitive_data' => true,
            'risk_level' => 'CRITICAL',
            'leak_type' => 'Credenciais',
            'justification' => 'Senha detectada.',
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        $this->service()->analyze($log);

        $this->assertSame('critical', $log->fresh()->risk_level);
    }

    public function test_transicao_atomica_impede_duplo_processamento(): void
    {
        $this->configureGemini();
        $this->fakeGemini([
            'has_sensitive_data' => false,
            'risk_level' => 'low',
            'leak_type' => 'Nenhum',
            'justification' => 'Sem dados sensíveis.',
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        // Simula o primeiro worker completando antes do segundo chegar à guarda
        $this->service()->analyze($log);
        $this->assertSame('completed', $log->fresh()->status);

        Http::fake(); // reseta o fake para garantir que nenhuma nova chamada seja feita

        // Segundo worker tenta processar o mesmo log — deve ser ignorado
        $this->service()->analyze($log->fresh());

        Http::assertNothingSent();
        $this->assertSame('completed', $log->fresh()->status);
    }
}
