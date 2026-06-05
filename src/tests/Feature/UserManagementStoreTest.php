<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class UserManagementStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_cadastra_usuario_sem_senha_informada(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/admin/users', [
            'name' => 'Novo Servidor',
            'email' => 'novo@orgao.gov.br',
            'role' => 'auditor',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['message', 'user']);

        $this->assertDatabaseHas('users', [
            'email' => 'novo@orgao.gov.br',
            'role' => 'auditor',
        ]);
    }

    public function test_auditor_nao_consegue_cadastrar_usuario(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->postJson('/admin/users', [
                'name' => 'Tentativa',
                'email' => 'tentativa@test.com',
                'role' => 'auditor',
            ])
            ->assertStatus(403);
    }

    public function test_email_de_reset_e_enviado_apos_cadastro(): void
    {
        $admin = User::factory()->admin()->create();

        Password::shouldReceive('sendResetLink')
            ->once()
            ->with(['email' => 'novo2@orgao.gov.br'])
            ->andReturn(Password::RESET_LINK_SENT);

        $this->actingAs($admin)->postJson('/admin/users', [
            'name' => 'Novo Servidor 2',
            'email' => 'novo2@orgao.gov.br',
            'role' => 'auditor',
        ])->assertStatus(201);
    }

    public function test_activity_log_criado_com_acao_user_created(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson('/admin/users', [
            'name' => 'Servidor Log',
            'email' => 'log@orgao.gov.br',
            'role' => 'admin',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.created',
            'user_id' => $admin->id,
        ]);

        // Garante que a senha não foi registrada
        $log = ActivityLog::where('action', 'user.created')->first();
        $this->assertStringNotContainsString('password', json_encode($log->properties));
    }

    public function test_validacao_impede_email_duplicado(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'ja.existe@orgao.gov.br']);

        $this->actingAs($admin)
            ->postJson('/admin/users', [
                'name' => 'Qualquer',
                'email' => 'ja.existe@orgao.gov.br',
                'role' => 'auditor',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_validacao_impede_role_invalido(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/admin/users', [
                'name' => 'Qualquer',
                'email' => 'qualquer@test.com',
                'role' => 'superadmin',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }
}
