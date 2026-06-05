<?php

namespace Tests\Feature;

use App\Jobs\ProcessAuditLog;
use App\Models\AiProviderSetting;
use App\Models\AuditLog;
use App\Services\Audit\AuditAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuditLogTokenTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGemini(array $analysis = []): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode(array_merge([
                        'has_sensitive_data' => false,
                        'risk_level' => 'low',
                        'justification' => 'Sem dados sensíveis.',
                        'leak_type' => 'Nenhum',
                    ], $analysis))]]],
                ]],
            ], 200),
        ]);
    }

    private function configureGemini(): void
    {
        AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'api_key_encrypted' => encrypt('AIzaFakeKeyForTokens'),
            'is_active' => true,
        ]);
    }

    public function test_factory_preenche_campos_de_tokens_ao_criar_log(): void
    {
        $log = AuditLog::factory()->create([
            'input_text' => 'Texto de entrada com dezesseis chars',
            'output_text' => 'Resposta de saída',
        ]);

        $this->assertGreaterThan(0, $log->input_tokens);
        $this->assertGreaterThan(0, $log->output_tokens);
        $this->assertSame($log->input_tokens + $log->output_tokens, $log->total_tokens);
    }

    public function test_with_tokens_state_define_valores_exatos(): void
    {
        $log = AuditLog::factory()->withTokens(100, 250)->create();
        $this->assertSame(100, $log->input_tokens);
        $this->assertSame(250, $log->output_tokens);
        $this->assertSame(350, $log->total_tokens);
    }

    public function test_job_preenche_tokens_ao_processar_log(): void
    {
        $this->configureGemini();
        $this->fakeGemini();

        $log = AuditLog::factory()->create([
            'status' => 'pending',
            'input_text' => 'abcdefgh',   // 8 chars → 2 tokens
            'output_text' => 'abcd',        // 4 chars → 1 token
            'input_tokens' => 0,
            'output_tokens' => 0,
            'total_tokens' => 0,
        ]);

        (new ProcessAuditLog($log->id))->handle(app(AuditAnalysisService::class));

        $log->refresh();
        $this->assertSame('completed', $log->status);
        $this->assertGreaterThan(0, $log->total_tokens);
        $this->assertSame($log->input_tokens + $log->output_tokens, $log->total_tokens);
        $this->assertNotNull($log->token_count_method);
    }

    public function test_job_usa_metadados_gemini_quando_disponiveis(): void
    {
        $this->configureGemini();
        // usageMetadata é chave de topo na resposta Gemini, não dentro do texto JSON
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'has_sensitive_data' => false,
                        'risk_level' => 'low',
                        'justification' => 'OK',
                        'leak_type' => 'Nenhum',
                    ])]]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 42, 'candidatesTokenCount' => 88, 'totalTokenCount' => 150],
            ], 200),
        ]);

        $log = AuditLog::factory()->create(['status' => 'pending']);

        (new ProcessAuditLog($log->id))->handle(app(AuditAnalysisService::class));

        $log->refresh();
        $this->assertSame(42, $log->input_tokens);
        $this->assertSame(88, $log->output_tokens);
        $this->assertSame(150, $log->total_tokens);
        $this->assertSame('api', $log->token_count_method);
    }
}
