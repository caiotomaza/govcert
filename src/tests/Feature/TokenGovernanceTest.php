<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\TokenAlert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_acessa_tela_de_tokens(): void
    {
        $user = User::factory()->auditor()->create();

        $this->actingAs($user)
            ->get('/tokens')
            ->assertStatus(200)
            ->assertSee('Tokens');
    }

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $this->get('/tokens')->assertRedirect('/login');
    }

    public function test_tela_exibe_ranking_de_usuarios(): void
    {
        $user = User::factory()->auditor()->create();
        AuditLog::factory()->withTokens(100, 200)->completed()->create([
            'user_identifier' => 'usuario-ranking@test.gov',
        ]);

        $this->actingAs($user)
            ->get('/tokens')
            ->assertStatus(200)
            ->assertSee('usuario-ranking@test.gov');
    }

    public function test_admin_cria_alerta_de_tokens(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/tokens/alerts', [
            'threshold_tokens' => 50000,
            'period' => 'monthly',
            'notify_auditors' => '1',
        ]);

        $response->assertRedirect('/tokens');
        $this->assertDatabaseHas('token_alerts', [
            'threshold_tokens' => 50000,
            'period' => 'monthly',
            'is_active' => true,
        ]);
    }

    public function test_auditor_pode_criar_alerta(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)->post('/tokens/alerts', [
            'threshold_tokens' => 10000,
            'period' => 'daily',
        ])->assertRedirect('/tokens');

        $this->assertDatabaseHas('token_alerts', ['threshold_tokens' => 10000]);
    }

    public function test_admin_pode_atualizar_alerta(): void
    {
        $admin = User::factory()->admin()->create();
        $alert = TokenAlert::factory()->create([
            'threshold_tokens' => 1000,
            'period' => 'daily',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->put("/tokens/alerts/{$alert->id}", [
            'threshold_tokens' => 99999,
            'period' => 'weekly',
            'is_active' => '1',
        ])->assertRedirect('/tokens');

        $this->assertDatabaseHas('token_alerts', [
            'id' => $alert->id,
            'threshold_tokens' => 99999,
            'period' => 'weekly',
        ]);
    }

    public function test_toggle_inverte_status_do_alerta(): void
    {
        $admin = User::factory()->admin()->create();
        $alert = TokenAlert::factory()->create([
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post("/tokens/alerts/{$alert->id}/toggle")
            ->assertRedirect('/tokens');

        $this->assertFalse($alert->fresh()->is_active);
    }

    public function test_admin_pode_excluir_alerta(): void
    {
        $admin = User::factory()->admin()->create();
        $alert = TokenAlert::factory()->create(['created_by' => $admin->id]);

        $this->actingAs($admin)
            ->delete("/tokens/alerts/{$alert->id}")
            ->assertRedirect('/tokens');

        $this->assertDatabaseMissing('token_alerts', ['id' => $alert->id]);
    }

    public function test_dashboard_exibe_kpis_de_tokens(): void
    {
        $user = User::factory()->create();
        AuditLog::factory()->withTokens(300, 500)->completed()->count(3)->create();

        $this->actingAs($user)
            ->get('/')
            ->assertStatus(200)
            ->assertSee('Total de Tokens')
            ->assertSee('Consumo de Tokens por Dia');
    }

    public function test_dashboard_ajax_retorna_token_stats(): void
    {
        $user = User::factory()->create();
        AuditLog::factory()->withTokens(100, 100)->completed()->create();

        $response = $this->actingAs($user)->get('/', [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['stats', 'tokenStats', 'volumeByDay', 'tokensByDay', 'topTokenUsers']);
    }
}
