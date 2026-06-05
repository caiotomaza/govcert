<?php

namespace Tests\Feature;

use App\Models\AiProviderSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_acessa_tela_de_configuracoes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/settings')
            ->assertStatus(200)
            ->assertSee('Configurações do GovCert');
    }

    public function test_auditor_recebe_403_na_tela_de_configuracoes(): void
    {
        $auditor = User::factory()->auditor()->create();

        $this->actingAs($auditor)
            ->get('/admin/settings')
            ->assertStatus(403);
    }

    public function test_admin_salva_provedor_com_chave_criptografada(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings/ai-provider', [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'api_key' => 'AIzaFakeKey1234567890',
            'timeout_seconds' => 30,
        ])->assertRedirect('/admin/settings');

        $setting = AiProviderSetting::where('provider', 'gemini')->first();
        $this->assertNotNull($setting);
        $this->assertTrue($setting->is_active);

        // Chave nunca salva em texto puro
        $this->assertNotEquals('AIzaFakeKey1234567890', $setting->api_key_encrypted);
        $this->assertEquals('AIzaFakeKey1234567890', $setting->decryptedApiKey());
    }

    public function test_chave_mascarada_nao_expoe_valor_completo(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put('/admin/settings/ai-provider', [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'api_key' => 'AIzaFakeKeyAbcdef1234',
        ]);

        $setting = AiProviderSetting::where('provider', 'gemini')->first();
        $masked = $setting->maskedApiKey();

        $this->assertStringNotContainsString('AIzaFakeKeyAbcdef1234', $masked);
        $this->assertStringContainsString('****', $masked);
    }

    public function test_tela_de_configuracoes_exibe_chave_mascarada(): void
    {
        $admin = User::factory()->admin()->create();

        $setting = AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'api_key_encrypted' => encrypt('AIzaFakeKeyXYZ9876'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->get('/admin/settings');
        $response->assertStatus(200);
        $response->assertDontSee('AIzaFakeKeyXYZ9876');
        $response->assertSee('****');
    }

    public function test_teste_de_api_retorna_sucesso_com_gemini_mockado(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'OK GovCert']]],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->admin()->create();

        AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'api_key_encrypted' => encrypt('AIzaFakeKey'),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/admin/settings/ai-provider/test', [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => true])
            ->assertJsonStructure(['success', 'provider', 'model', 'http_status', 'response_time_ms']);
    }

    public function test_teste_de_api_retorna_erro_tratado_sem_vazar_chave(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('API_KEY_INVALID error', 403),
        ]);

        $admin = User::factory()->admin()->create();
        $key = 'AIzaFakeSecretKey123';

        AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'api_key_encrypted' => encrypt($key),
            'is_active' => true,
        ]);

        $response = $this->actingAs($admin)->postJson('/admin/settings/ai-provider/test', [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
        ]);

        $response->assertStatus(200)
            ->assertJsonFragment(['success' => false]);

        // Chave real não pode aparecer na resposta
        $this->assertStringNotContainsString($key, $response->content());
    }

    public function test_logout_all_remove_sessoes_e_tokens(): void
    {
        $admin = User::factory()->admin()->create();

        // Simula um token Sanctum
        $admin->createToken('extensao-chrome');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->actingAs($admin)
            ->post('/admin/settings/logout-all')
            ->assertRedirect('/login');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_interface_exibe_visao_geral_e_nao_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/');
        $response->assertStatus(200)
            ->assertSee('Visão Geral');
    }

    public function test_interface_exibe_auditoria_e_nao_logs_de_auditoria(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/audit/logs');
        $response->assertStatus(200)
            ->assertSee('Auditoria');
    }

    public function test_admin_ve_opcao_configuracoes_do_govcert_na_navbar(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/');
        $response->assertStatus(200)
            ->assertSee('Configurações do GovCert');
    }

    public function test_auditor_nao_ve_configuracoes_do_govcert_na_navbar(): void
    {
        $auditor = User::factory()->auditor()->create();

        $response = $this->actingAs($auditor)->get('/');
        $response->assertStatus(200)
            ->assertDontSee('Configurações do GovCert');
    }
}
