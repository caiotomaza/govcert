<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogDetailTest extends TestCase
{
    use RefreshDatabase;

    // ── Dados sempre mascarados ───────────────────────────────────────────────

    public function test_auditor_ve_conteudo_mascarado_no_detalhe(): void
    {
        $auditor = User::factory()->auditor()->create();
        $log = AuditLog::factory()->completed('critical')->create([
            'input_text' => 'Meu CPF é 123.456.789-00',
            'output_text' => 'Resposta sobre o CPF do usuário',
        ]);

        $response = $this->actingAs($auditor)
            ->getJson("/audit/logs/{$log->id}/detail");

        $response->assertStatus(200);
        $this->assertStringContainsString('[CPF OCULTO]', $response->json('input_masked'));
        $this->assertStringNotContainsString('123.456.789-00', $response->json('input_masked'));
    }

    public function test_admin_ve_conteudo_mascarado_no_detalhe(): void
    {
        $admin = User::factory()->admin()->create();
        $log = AuditLog::factory()->completed('critical')->create([
            'input_text' => 'E-mail: joao@orgao.gov.br, CPF: 987.654.321-00',
            'output_text' => 'Dados processados',
        ]);

        $response = $this->actingAs($admin)
            ->getJson("/audit/logs/{$log->id}/detail");

        $response->assertStatus(200);
        $this->assertStringNotContainsString('joao@orgao.gov.br', $response->json('input_masked'));
        $this->assertStringNotContainsString('987.654.321-00', $response->json('input_masked'));
        $this->assertStringContainsString('[CPF OCULTO]', $response->json('input_masked'));
        $this->assertStringContainsString('[E-MAIL OCULTO]', $response->json('input_masked'));
    }

    public function test_resposta_nao_contem_dados_sensiveis_originais(): void
    {
        $user = User::factory()->create();
        $log = AuditLog::factory()->completed()->create([
            'input_text' => 'Preciso de ajuda. password=Senh@SecretXYZ123 e email joao@secret.gov.br',
            'output_text' => 'CPF: 111.222.333-44 detectado no contexto.',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/audit/logs/{$log->id}/detail");

        $response->assertStatus(200);

        $content = $response->content();

        // Valores sensíveis nunca devem aparecer na resposta
        $this->assertStringNotContainsString('Senh@SecretXYZ123', $content);
        $this->assertStringNotContainsString('joao@secret.gov.br', $content);
        $this->assertStringNotContainsString('111.222.333-44', $content);

        // O masker substitui por marcadores
        $this->assertStringContainsString('[CREDENCIAL OCULTA]', $content);
        $this->assertStringContainsString('[E-MAIL OCULTO]', $content);
        $this->assertStringContainsString('[CPF OCULTO]', $content);

        // Chaves com texto original não existem no JSON
        $this->assertArrayNotHasKey('input_text', $response->json());
        $this->assertArrayNotHasKey('output_text', $response->json());
    }

    public function test_endpoint_reveal_nao_existe(): void
    {
        $admin = User::factory()->admin()->create();
        $log = AuditLog::factory()->completed()->create();

        // A rota de reveal foi removida — deve retornar 404 (ou 405)
        $this->actingAs($admin)
            ->postJson("/audit/logs/{$log->id}/reveal")
            ->assertStatus(404);
    }

    public function test_resposta_nao_tem_can_reveal(): void
    {
        $admin = User::factory()->admin()->create();
        $log = AuditLog::factory()->completed()->create();

        $response = $this->actingAs($admin)
            ->getJson("/audit/logs/{$log->id}/detail");

        $this->assertArrayNotHasKey('can_reveal', $response->json());
    }

    // ── Tokens apenas do log ─────────────────────────────────────────────────

    public function test_detalhe_mostra_tokens_do_log_especifico(): void
    {
        $user = User::factory()->auditor()->create();
        $log = AuditLog::factory()->withTokens(100, 200)->completed()->create();

        $response = $this->actingAs($user)
            ->getJson("/audit/logs/{$log->id}/detail");

        $response->assertStatus(200)
            ->assertJsonFragment([
                'input_tokens' => 100,
                'output_tokens' => 200,
                'total_tokens' => 300,
            ]);
    }

    public function test_detalhe_nao_mostra_cumulative_tokens(): void
    {
        $user = User::factory()->auditor()->create();
        $log = AuditLog::factory()->withTokens(50, 50)->completed()->create();

        $response = $this->actingAs($user)
            ->getJson("/audit/logs/{$log->id}/detail");

        $this->assertArrayNotHasKey('cumulative_tokens', $response->json());
    }

    public function test_endpoint_detail_exige_autenticacao(): void
    {
        $log = AuditLog::factory()->completed()->create();

        $this->getJson("/audit/logs/{$log->id}/detail")
            ->assertStatus(401);
    }

    // ── Exibição por status ───────────────────────────────────────────────────

    public function test_resposta_inclui_status_do_log(): void
    {
        $user = User::factory()->auditor()->create();
        $log  = AuditLog::factory()->create(['status' => 'pending']);

        $this->actingAs($user)
            ->getJson("/audit/logs/{$log->id}/detail")
            ->assertStatus(200)
            ->assertJsonFragment(['status' => 'pending']);
    }

    // ── Controle de error_reason por papel ───────────────────────────────────

    public function test_admin_recebe_error_reason_sanitizado_em_log_failed(): void
    {
        $admin = User::factory()->admin()->create();
        $log   = AuditLog::factory()->create([
            'status' => 'failed',
            'error_reason' => 'Gemini API error: Request failed (status 429)',
        ]);

        $response = $this->actingAs($admin)->getJson("/audit/logs/{$log->id}/detail");

        $response->assertStatus(200);
        $this->assertNotNull($response->json('error_reason'));
        $this->assertStringContainsString('429', $response->json('error_reason'));
    }

    public function test_auditor_recebe_error_reason_nulo_em_log_failed(): void
    {
        $auditor = User::factory()->auditor()->create();
        $log     = AuditLog::factory()->create([
            'status' => 'failed',
            'error_reason' => 'Detalhe técnico interno que auditor não deve ver',
        ]);

        $response = $this->actingAs($auditor)->getJson("/audit/logs/{$log->id}/detail");

        $response->assertStatus(200);
        $this->assertNull($response->json('error_reason'));
        $this->assertStringNotContainsString('Detalhe técnico interno', $response->content());
    }

    public function test_error_reason_sanitiza_padroes_de_api_key(): void
    {
        $admin = User::factory()->admin()->create();

        // API key residual (não deveria existir após sanitizeError, mas testamos a segunda camada)
        $log = AuditLog::factory()->create([
            'status' => 'failed',
            'error_reason' => 'Erro: AIzaSyABCDEF1234567890abcdefghijklmnop (key inválida)',
        ]);

        $response = $this->actingAs($admin)->getJson("/audit/logs/{$log->id}/detail");

        $this->assertStringNotContainsString('AIzaSyABCDEF1234567890abcdefghijklmnop', $response->content());
        $this->assertStringContainsString('[CHAVE GOOGLE]', $response->json('error_reason'));
    }

    public function test_error_reason_vazio_retorna_mensagem_padrao_para_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $log   = AuditLog::factory()->create([
            'status' => 'failed',
            'error_reason' => null,
        ]);

        $response = $this->actingAs($admin)->getJson("/audit/logs/{$log->id}/detail");

        $this->assertStringContainsString('nenhum motivo técnico', $response->json('error_reason'));
    }
}
