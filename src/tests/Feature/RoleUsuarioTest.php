<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RoleUsuarioTest extends TestCase
{
    use RefreshDatabase;

    // ── Cadastro com perfil usuario ───────────────────────────────────────────

    public function test_admin_cadastra_usuario_com_perfil_usuario(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/admin/users', [
            'name' => 'Servidor Monitorado',
            'email' => 'monitorado@orgao.gov.br',
            'role' => 'usuario',
        ])->assertStatus(201);

        $this->assertDatabaseHas('users', [
            'email' => 'monitorado@orgao.gov.br',
            'role' => 'usuario',
        ]);
    }

    // ── Autenticação na extensão ─────────────────────────────────────────────

    public function test_usuario_autentica_na_extensao(): void
    {
        $user = User::factory()->usuario()->create(['password' => bcrypt('senha123')]);

        $this->postJson('/api/extension/login', [
            'email' => $user->email,
            'password' => 'senha123',
            'device_name' => 'extensao-test',
        ])->assertStatus(200)
            ->assertJsonStructure(['token', 'user_name', 'user_email']);
    }

    // ── Envio de log pela API ─────────────────────────────────────────────────

    public function test_usuario_ativo_envia_log_pela_api(): void
    {
        Queue::fake(); // Impede execução do job ProcessAuditLog

        $user = User::factory()->usuario()->create();
        $token = $user->createToken('extensao')->plainTextToken;

        $this->withToken($token)->postJson('/api/audit/logs', [
            'user_identifier' => $user->email,
            'input_text' => 'Texto de teste',
            'output_text' => 'Resposta de teste',
            'url_source' => 'https://chatgpt.com/c/test',
        ])->assertStatus(202);
    }

    public function test_usuario_inativo_nao_envia_log_pela_api(): void
    {
        $user = User::factory()->usuario()->inactive()->create();
        $token = $user->createToken('extensao')->plainTextToken;

        $this->withToken($token)->postJson('/api/audit/logs', [
            'user_identifier' => $user->email,
            'input_text' => 'Teste',
            'output_text' => 'Resposta',
            'url_source' => 'https://chatgpt.com/c/test',
        ])->assertStatus(403);
    }

    // ── Bloqueio do painel web ────────────────────────────────────────────────

    public function test_usuario_nao_acessa_painel_web(): void
    {
        $user = User::factory()->usuario()->create(['password' => bcrypt('senha123')]);

        // Login web → redireciona para / → middleware CheckUserStatus barra e redireciona para /login
        $response = $this->followingRedirects()->post('/login', [
            'email' => $user->email,
            'password' => 'senha123',
        ]);

        // Deve acabar na tela de login com mensagem
        $response->assertSee('extensão GovCert');
    }

    public function test_usuario_autenticado_no_painel_e_redirecionado_ao_login(): void
    {
        $user = User::factory()->usuario()->create();

        // Mesmo logado via actingAs, o middleware deve barrar o acesso ao painel
        $this->actingAs($user)
            ->get('/')
            ->assertRedirect('/login');
    }

    public function test_usuario_nao_acessa_auditoria(): void
    {
        $user = User::factory()->usuario()->create();

        $this->actingAs($user)
            ->get('/audit/logs')
            ->assertRedirect('/login');
    }

    public function test_usuario_nao_acessa_tela_de_tokens(): void
    {
        $user = User::factory()->usuario()->create();

        $this->actingAs($user)
            ->get('/tokens')
            ->assertRedirect('/login');
    }

    // ── Outros perfis não afetados ────────────────────────────────────────────

    public function test_admin_continua_acessando_painel(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/')->assertStatus(200);
    }

    public function test_auditor_continua_acessando_painel(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)->get('/')->assertStatus(200);
    }

    // ── Aba Tokens renomeada ─────────────────────────────────────────────────

    public function test_tela_de_tokens_exibe_titulo_tokens(): void
    {
        $user = User::factory()->auditor()->create();

        $this->actingAs($user)
            ->get('/tokens')
            ->assertStatus(200)
            ->assertSee('Tokens')
            ->assertDontSee('Governança de Tokens');
    }
}
