<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\AiProviderSetting;
use App\Models\AuditLog;
use App\Models\TokenAlert;
use App\Models\TokenAlertEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->seedUsers();
        $this->seedAuditLogs($users);
        $this->seedTokenAlerts($users);
        $this->seedSystemSettings();
        $this->seedActivityLogs($users);

        $totalUsers = User::count();
        $totalLogs = AuditLog::count();

        $this->command->newLine();
        $this->command->info("✓ Seed concluído: {$totalUsers} usuários, {$totalLogs} logs de auditoria.");
        $this->command->newLine();
        $this->command->line('  Painel (admin):   admin@govcert.gov.br / admin123');
        $this->command->line('  Painel (auditor): auditor@govcert.gov.br / auditor123');
        $this->command->line('  Extensão:         maria.silva@govcert.gov.br / usuario123');
        $this->command->line('  Inativo (bloq.):  bloqueado@govcert.gov.br / usuario123');
        $this->command->newLine();
    }

    // ── Usuários ─────────────────────────────────────────────────────────────

    private function seedUsers(): Collection
    {
        // Admins
        $admin = User::factory()->admin()->create([
            'name' => 'Administrador GovCert',
            'email' => 'admin@govcert.gov.br',
            'password' => Hash::make('admin123'),
        ]);

        $chefe = User::factory()->admin()->create([
            'name' => 'Chefe de Segurança',
            'email' => 'chefe@govcert.gov.br',
            'password' => Hash::make('admin123'),
        ]);

        // Auditor
        $auditor = User::factory()->auditor()->create([
            'name' => 'Auditor GovCert',
            'email' => 'auditor@govcert.gov.br',
            'password' => Hash::make('auditor123'),
        ]);

        // Usuários comuns — perfis distintos de consumo de tokens
        $maria = User::factory()->usuario()->create([
            'name' => 'Maria Silva',
            'email' => 'maria.silva@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        $joao = User::factory()->usuario()->create([
            'name' => 'João Santos',
            'email' => 'joao.santos@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        $ana = User::factory()->usuario()->create([
            'name' => 'Ana Lima',
            'email' => 'ana.lima@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        $pedro = User::factory()->usuario()->create([
            'name' => 'Pedro Costa',
            'email' => 'pedro.costa@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        $lucas = User::factory()->usuario()->create([
            'name' => 'Lucas Ferreira',
            'email' => 'lucas.ferreira@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        $carla = User::factory()->usuario()->create([
            'name' => 'Carla Moura',
            'email' => 'carla.moura@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        $rafael = User::factory()->usuario()->create([
            'name' => 'Rafael Almeida',
            'email' => 'rafael.almeida@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        $bianca = User::factory()->usuario()->create([
            'name' => 'Bianca Rocha',
            'email' => 'bianca.rocha@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        // Usuário inativo — para testar bloqueio da API
        User::factory()->usuario()->inactive()->create([
            'name' => 'Usuário Bloqueado',
            'email' => 'bloqueado@govcert.gov.br',
            'password' => Hash::make('usuario123'),
        ]);

        return collect(compact(
            'admin', 'chefe', 'auditor',
            'maria', 'joao', 'ana', 'pedro',
            'lucas', 'carla', 'rafael', 'bianca'
        ));
    }

    // ── Logs de auditoria ────────────────────────────────────────────────────

    private function seedAuditLogs(Collection $users): void
    {
        /**
         * Perfis de consumo (consumption) e volume (count):
         *
         * admin    → alto (40 logs) — usa extensão p/ auditoria interna
         * chefe    → médio-alto (30 logs)
         * auditor  → médio (20 logs)
         * maria    → baixo consumo: poucos logs, poucos tokens
         * joao     → médio consumo
         * ana      → alto consumo: muitos logs, tokens altos
         * pedro    → crítico: 100+ logs, tokens muito altos
         * lucas    → muitos logs pequenos (heatmap de qtd alta, tokens baixo)
         * carla    → poucos logs enormes (heatmap de tokens alto, qtd baixo)
         * rafael   → alto risco c/ dados sensíveis
         * bianca   → uso normal, sem dados sensíveis
         */
        $profiles = [
            'admin' => ['count' => 40,  'consumption' => 'high'],
            'chefe' => ['count' => 30,  'consumption' => 'medium'],
            'auditor' => ['count' => 20,  'consumption' => 'medium'],
            'maria' => ['count' => 7,   'consumption' => 'low'],
            'joao' => ['count' => 22,  'consumption' => 'medium'],
            'ana' => ['count' => 55,  'consumption' => 'high'],
            'pedro' => ['count' => 110, 'consumption' => 'critical'],
            'lucas' => ['count' => 80,  'consumption' => 'low'],
            'carla' => ['count' => 8,   'consumption' => 'critical'],
            'rafael' => ['count' => 35,  'consumption' => 'high'],
            'bianca' => ['count' => 18,  'consumption' => 'low'],
        ];

        foreach ($profiles as $key => $profile) {
            $user = $users[$key];

            foreach (range(1, $profile['count']) as $_) {
                AuditLog::factory()
                    ->scenario($profile['consumption'])
                    ->create([
                        'user_id' => $user->id,
                        'user_identifier' => $user->email,
                    ]);
            }
        }
    }

    // ── Alertas de tokens ────────────────────────────────────────────────────

    private function seedTokenAlerts(Collection $users): void
    {
        // Alerta 1 — Pedro Costa: limite mensal ultrapassado (critical)
        $alertPedro = TokenAlert::create([
            'user_id' => $users['pedro']->id,
            'threshold_tokens' => 50_000,
            'period' => 'monthly',
            'notify_user' => true,
            'notify_auditors' => true,
            'notify_admins' => true,
            'notify_email' => null,
            'is_active' => true,
            'created_by' => $users['admin']->id,
            'last_triggered_at' => now()->subHours(3),
        ]);

        // Evento de disparo — Pedro já ultrapassou
        TokenAlertEvent::create([
            'token_alert_id' => $alertPedro->id,
            'user_id' => $users['pedro']->id,
            'current_tokens' => AuditLog::where('user_id', $users['pedro']->id)->sum('total_tokens'),
            'threshold_tokens' => 50_000,
            'triggered_at' => now()->subHours(3),
            'notified_targets' => [$users['pedro']->email, $users['admin']->email, $users['auditor']->email],
        ]);

        // Alerta 2 — Ana Lima: mensal ativo
        TokenAlert::create([
            'user_id' => $users['ana']->id,
            'threshold_tokens' => 30_000,
            'period' => 'monthly',
            'notify_user' => false,
            'notify_auditors' => true,
            'notify_admins' => false,
            'notify_email' => null,
            'is_active' => true,
            'created_by' => $users['admin']->id,
            'last_triggered_at' => null,
        ]);

        // Alerta 3 — Carla Moura: semanal ativo
        TokenAlert::create([
            'user_id' => $users['carla']->id,
            'threshold_tokens' => 20_000,
            'period' => 'weekly',
            'notify_user' => true,
            'notify_auditors' => true,
            'notify_admins' => false,
            'notify_email' => null,
            'is_active' => true,
            'created_by' => $users['chefe']->id,
            'last_triggered_at' => null,
        ]);

        // Alerta 4 — Global mensal
        $alertGlobal = TokenAlert::create([
            'user_id' => null,
            'threshold_tokens' => 100_000,
            'period' => 'monthly',
            'notify_user' => false,
            'notify_auditors' => true,
            'notify_admins' => true,
            'notify_email' => null,
            'is_active' => true,
            'created_by' => $users['admin']->id,
            'last_triggered_at' => now()->subDay(),
        ]);

        // Evento de disparo — alerta global
        TokenAlertEvent::create([
            'token_alert_id' => $alertGlobal->id,
            'user_id' => null,
            'current_tokens' => AuditLog::sum('total_tokens'),
            'threshold_tokens' => 100_000,
            'triggered_at' => now()->subDay(),
            'notified_targets' => [$users['admin']->email, $users['auditor']->email],
        ]);

        // Alerta 5 — Lucas Ferreira: diário inativo
        TokenAlert::create([
            'user_id' => $users['lucas']->id,
            'threshold_tokens' => 10_000,
            'period' => 'daily',
            'notify_user' => true,
            'notify_auditors' => false,
            'notify_admins' => false,
            'notify_email' => null,
            'is_active' => false,
            'created_by' => $users['chefe']->id,
            'last_triggered_at' => null,
        ]);
    }

    // ── Configurações do sistema ──────────────────────────────────────────────

    private function seedSystemSettings(): void
    {
        // Provedor ativo — Gemini sem chave real (use .env em dev)
        AiProviderSetting::create([
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'base_url' => null,
            'api_key_encrypted' => null,
            'timeout_seconds' => 30,
            'is_active' => true,
            'last_tested_at' => null,
            'last_test_status' => null,
        ]);

        // DeepSeek inativo
        AiProviderSetting::create([
            'provider' => 'deepseek',
            'model' => 'deepseek-chat',
            'base_url' => 'https://api.deepseek.com/v1',
            'api_key_encrypted' => null,
            'timeout_seconds' => 30,
            'is_active' => false,
            'last_tested_at' => null,
            'last_test_status' => null,
        ]);

        // Grok inativo
        AiProviderSetting::create([
            'provider' => 'grok',
            'model' => 'grok-beta',
            'base_url' => 'https://api.x.ai/v1',
            'api_key_encrypted' => null,
            'timeout_seconds' => 30,
            'is_active' => false,
            'last_tested_at' => null,
            'last_test_status' => null,
        ]);
    }

    // ── Trilha de atividades ──────────────────────────────────────────────────

    private function seedActivityLogs(Collection $users): void
    {
        $entries = [
            [
                'user_id' => $users['admin']->id,
                'action' => 'auth.login',
                'description' => 'Autenticou no sistema',
                'subject_type' => null,
                'subject_id' => null,
                'ip_address' => '192.168.1.10',
                'properties' => null,
                'created_at' => now()->subDays(30),
            ],
            [
                'user_id' => $users['admin']->id,
                'action' => 'settings.ai-provider-updated',
                'description' => 'Configuração de provedor de IA atualizada: Gemini (gemini-2.5-flash).',
                'subject_type' => AiProviderSetting::class,
                'subject_id' => 1,
                'ip_address' => '192.168.1.10',
                'properties' => ['provider' => 'gemini', 'model' => 'gemini-2.5-flash'],
                'created_at' => now()->subDays(29),
            ],
            [
                'user_id' => $users['admin']->id,
                'action' => 'settings.ai-provider-tested',
                'description' => 'Teste de API do provedor Gemini (gemini-2.5-flash): sucesso.',
                'subject_type' => null,
                'subject_id' => null,
                'ip_address' => '192.168.1.10',
                'properties' => ['provider' => 'gemini', 'model' => 'gemini-2.5-flash', 'success' => true],
                'created_at' => now()->subDays(29),
            ],
            [
                'user_id' => $users['admin']->id,
                'action' => 'user.created',
                'description' => 'Cadastrou o usuário pedro.costa@govcert.gov.br com perfil usuario.',
                'subject_type' => User::class,
                'subject_id' => $users['pedro']->id,
                'ip_address' => '192.168.1.10',
                'properties' => ['role' => 'usuario', 'email' => 'pedro.costa@govcert.gov.br'],
                'created_at' => now()->subDays(28),
            ],
            [
                'user_id' => $users['chefe']->id,
                'action' => 'user.updated',
                'description' => 'Editou o usuário auditor@govcert.gov.br',
                'subject_type' => User::class,
                'subject_id' => $users['auditor']->id,
                'ip_address' => '10.0.0.5',
                'properties' => ['de' => ['name' => 'Auditor'], 'para' => ['name' => 'Auditor GovCert']],
                'created_at' => now()->subDays(15),
            ],
            [
                'user_id' => $users['admin']->id,
                'action' => 'token_alert.triggered',
                'description' => 'Alerta de tokens disparado: limite de 50000 tokens (monthly) atingido com 150000 tokens.',
                'subject_type' => TokenAlert::class,
                'subject_id' => 1,
                'ip_address' => '127.0.0.1',
                'properties' => ['alert_id' => 1, 'current_tokens' => 150000],
                'created_at' => now()->subHours(3),
            ],
            [
                'user_id' => $users['auditor']->id,
                'action' => 'auth.login',
                'description' => 'Autenticou no sistema',
                'subject_type' => null,
                'subject_id' => null,
                'ip_address' => '10.0.0.8',
                'properties' => null,
                'created_at' => now()->subHours(2),
            ],
        ];

        foreach ($entries as $entry) {
            ActivityLog::create([
                'user_id' => $entry['user_id'],
                'action' => $entry['action'],
                'description' => $entry['description'],
                'subject_type' => $entry['subject_type'] ?? null,
                'subject_id' => $entry['subject_id'] ?? null,
                'ip_address' => $entry['ip_address'],
                'user_agent' => 'GovCert/Seed',
                'properties' => $entry['properties'] ?? null,
                'created_at' => $entry['created_at'],
            ]);
        }
    }
}
