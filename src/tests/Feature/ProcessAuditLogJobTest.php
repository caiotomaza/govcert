<?php

namespace Tests\Feature;

use App\Jobs\ProcessAuditLog;
use App\Models\AiProviderSetting;
use App\Models\AuditLog;
use App\Services\Audit\AuditAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessAuditLogJobTest extends TestCase
{
    use RefreshDatabase;

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

    private function configureGemini(string $model = 'gemini-2.5-flash'): void
    {
        AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => $model,
            'api_key_encrypted' => encrypt('AIzaFakeKeyForJob'),
            'is_active' => true,
        ]);
    }

    private function runJob(int $logId): void
    {
        (new ProcessAuditLog($logId))->handle(app(AuditAnalysisService::class));
    }

    public function test_job_processa_log_pendente_e_marca_como_completed(): void
    {
        $this->configureGemini();
        $this->fakeGemini([
            'has_sensitive_data' => true,
            'risk_level' => 'critical',
            'justification' => 'CPF e dados bancários detectados no input.',
            'detected_categories' => ['cpf', 'dados_bancarios'],
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        $this->runJob($log->id);

        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertTrue($log->has_sensitive_data);
        $this->assertSame('critical', $log->risk_level);
        $this->assertSame('CPF e dados bancários detectados no input.', $log->gemini_justification);
        $this->assertNotNull($log->processed_at);
        $this->assertIsArray($log->gemini_raw_response);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'models/gemini-2.5-flash:generateContent'));
    }

    public function test_job_marca_como_failed_quando_gemini_retorna_erro(): void
    {
        $this->configureGemini();
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('erro interno', 500),
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        $this->runJob($log->id);

        $this->assertSame('failed', $log->fresh()->status);
    }

    public function test_job_marca_failed_quando_nao_ha_api_key_configurada(): void
    {
        Http::fake();
        config(['services.gemini.api_key' => null]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        $this->runJob($log->id);

        $log->refresh();
        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('API key', $log->error_reason);
        Http::assertNothingSent();
    }

    public function test_job_ignora_log_que_ja_esta_completed(): void
    {
        Http::fake();

        $log = AuditLog::factory()->completed('low', false)->create();

        $this->runJob($log->id);

        Http::assertNothingSent();
        $this->assertSame('completed', $log->fresh()->status);
    }
}
