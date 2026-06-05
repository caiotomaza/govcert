# 03 - Estrutura de Pastas

## Visão Geral do Repositório

```
govcert/
├── chrome-extension/       # Extensão Chrome MV3 (sem build — carga direta)
├── docker/                 # Configurações de infraestrutura
├── docs/                   # Documentação técnica
├── src/                    # Aplicação Laravel 12
├── docker-compose.yml
├── README.md
└── RUNBOOK.md
```

---

## chrome-extension/

```
chrome-extension/
├── manifest.json           # Declaração MV3: permissões, content scripts, service worker
├── content.js              # Injeta nas páginas monitoradas; captura input/output do DOM
├── background.js           # Service worker; envia HTTP para a API; gerencia token
├── popup.html              # Interface do ícone da extensão
├── popup.js                # Login, status de conexão, logout
├── options.html            # Página de opções adicionais
├── options.js              # Lógica das opções
└── icons/
    ├── icon16.png
    ├── icon48.png
    └── icon128.png
```

A extensão **não tem build**. Os arquivos são carregados diretamente pelo Chrome em modo desenvolvedor ou empacotados com `chrome://extensions`.

---

## docker/

```
docker/
├── nginx/
│   └── default.conf        # Configuração do virtual host Nginx
└── php/
    └── Dockerfile          # PHP 8.2-FPM + extensões + Composer + Node
```

---

## src/ — Aplicação Laravel

### app/

```
src/app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/                            # Endpoints consumidos pela extensão e API
│   │   │   ├── AuditController.php         # POST /api/audit/logs (ingestão)
│   │   │   ├── AuthController.php          # Login/logout API genérico
│   │   │   ├── ExtensionAuthController.php # Login/logout da extensão Chrome
│   │   │   └── PasswordResetController.php # Reset de senha via API
│   │   │
│   │   ├── Web/                            # Controllers do painel web
│   │   │   ├── AuditLogController.php      # Listagem, filtros, exportações
│   │   │   ├── AuditLogDetailController.php# Detalhe mascarado (AJAX)
│   │   │   ├── AuthController.php          # Login/logout web
│   │   │   ├── DashboardController.php     # Visão Geral com KPIs e gráficos
│   │   │   ├── ApiTokenController.php      # Gerenciar token da extensão pelo perfil
│   │   │   ├── PasswordResetController.php # Reset de senha via formulário web
│   │   │   ├── ProfileController.php       # Editar perfil e alterar senha
│   │   │   └── TokenGovernanceController.php # Aba Tokens, ranking, alertas CRUD
│   │   │
│   │   └── Admin/                          # Controllers administrativos
│   │       ├── ActivityLogController.php   # Listagem do ActivityLog
│   │       ├── SystemSettingsController.php# Configurações do GovCert
│   │       └── UserController.php          # CRUD de usuários
│   │
│   └── Middleware/
│       ├── CheckUserStatus.php             # Bloqueia inativo; redireciona 'usuario'
│       └── IsAdmin.php                     # 403 para não-admin
│
├── Jobs/
│   └── ProcessAuditLog.php                 # Worker: busca config de IA, analisa, salva
│
├── Models/
│   ├── ActivityLog.php                     # Trilha de auditoria; método record()
│   ├── AiProviderSetting.php               # Config de provedor de IA; criptografia de key
│   ├── AuditLog.php                        # Log central; campos de tokens; masking via Service
│   ├── TokenAlert.php                      # Alertas de limite de tokens
│   ├── TokenAlertEvent.php                 # Eventos de disparo de alertas
│   └── User.php                            # Usuários; isAdmin(), isAuditor(), isUsuario()
│
├── Providers/
│   └── AppServiceProvider.php             # Personalização do e-mail de reset de senha
│
└── Services/
    ├── Ai/
    │   ├── Contracts/
    │   │   └── AiProviderClient.php        # Interface: testConnection(), analyzeAuditLog()
    │   ├── AiProviderManager.php           # Seleciona cliente ativo; fallback .env
    │   ├── AiProviderTestResult.php        # DTO para resultado de teste de API
    │   ├── GeminiClient.php                # Cliente Google Gemini (API própria)
    │   └── OpenAiCompatibleClient.php      # Cliente Grok/DeepSeek/Custom (OpenAI-compat)
    │
    ├── SensitiveDataMasker.php             # Regex: CPF, CNPJ, e-mail, senha, token...
    ├── TokenAlertService.php               # Verifica alertas; registra eventos
    ├── TokenCounter.php                    # Estimativa de tokens; integração com metadata
    └── TokenUsageService.php               # Ranking, heatmap, stats por período
```

### database/

```
src/database/
├── factories/
│   ├── AuditLogFactory.php                 # 14 cenários; estados low/medium/high/critical
│   ├── TokenAlertFactory.php               # Factory de alertas para testes
│   └── UserFactory.php                     # admin(), auditor(), usuario(), inactive()
│
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php
│   ├── 2026_05_07_220411_create_personal_access_tokens_table.php
│   ├── 2026_05_15_000001_create_audit_logs_table.php
│   ├── 2026_05_15_000002_add_role_and_status_to_users_table.php
│   ├── 2026_05_15_000003_create_activity_logs_table.php
│   ├── 2026_05_15_000004_add_leak_type_to_audit_logs_table.php
│   ├── 2026_05_18_000001_update_audit_logs_status_and_add_error_reason.php
│   ├── 2026_06_01_000001_add_token_usage_columns_to_audit_logs_table.php
│   ├── 2026_06_01_000002_create_token_alerts_table.php
│   ├── 2026_06_01_000003_create_token_alert_events_table.php
│   ├── 2026_06_01_000004_create_ai_provider_settings_table.php
│   └── 2026_06_01_000005_add_usuario_role_to_users_table.php
│
└── seeders/
    └── DatabaseSeeder.php                  # Organizado em métodos privados por domínio
```

### resources/views/

```
src/resources/views/
├── admin/
│   ├── activity/
│   │   └── index.blade.php                 # Listagem do ActivityLog
│   ├── settings/
│   │   └── index.blade.php                 # Configurações do GovCert
│   └── users/
│       └── index.blade.php                 # Gestão de usuários (cadastro modal)
│
├── audit/
│   ├── index.blade.php                     # Listagem de logs + modal de detalhe
│   ├── _table.blade.php                    # Partial AJAX da tabela de logs
│   └── pdf.blade.php                       # Template para exportação PDF
│
├── auth/
│   ├── login.blade.php
│   └── reset-password.blade.php
│
├── components/
│   ├── button.blade.php                    # Botão com variantes (primary, danger, soft...)
│   ├── modal.blade.php                     # Modal reutilizável
│   ├── navbar.blade.php                    # Navbar com dropdown do usuário
│   └── status-badge.blade.php             # Badge de status com tooltip de erro
│
├── dashboard/
│   └── index.blade.php                     # Visão Geral com KPIs e gráficos ApexCharts
│
├── layouts/
│   └── app.blade.php                       # Layout principal (Tailwind CDN + ApexCharts)
│
├── profile/
│   ├── api-tokens.blade.php                # Gerenciar token da extensão
│   └── edit.blade.php                      # Editar perfil e alterar senha
│
└── tokens/
    └── index.blade.php                     # Aba Tokens: KPIs, ranking, heatmap, alertas
```

### routes/

```
src/routes/
├── api.php                                 # Rotas da extensão e API REST
├── console.php                             # Comandos artisan e scheduler
└── web.php                                 # Rotas do painel web
```

### tests/

```
src/tests/
├── Feature/
│   ├── ApiAuthTest.php
│   ├── ApiTokenTest.php
│   ├── AuditIngestionTest.php
│   ├── AuditLogDetailTest.php              # Mascaramento, tokens, sem reveal
│   ├── AuditLogTokenTest.php               # Contagem de tokens
│   ├── DashboardAccessTest.php
│   ├── ProcessAuditLogJobTest.php
│   ├── RoleUsuarioTest.php                 # Perfil usuario: extensão, bloqueio no painel
│   ├── SystemSettingsTest.php              # Configurações do GovCert, logout all
│   ├── TokenGovernanceTest.php
│   └── UserManagementTest.php
│   └── UserManagementStoreTest.php         # Cadastro via modal
│
└── Unit/
    ├── AuditLogTest.php
    ├── SensitiveDataMaskerTest.php         # Mascaramento CPF, CNPJ, e-mail, etc.
    └── TokenCounterTest.php
```

---

## Relacionados

- [[02 - Arquitetura]]
- [[07 - Módulos e Componentes]]
- [[08 - Banco de Dados]]
- [[17 - Testes]]
