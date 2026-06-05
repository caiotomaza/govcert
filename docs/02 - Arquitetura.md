# 02 - Arquitetura

## Visão Geral das Camadas

O GovCert é dividido em sete camadas que operam de forma desacoplada:

```
┌─────────────────────────────────────────────────────┐
│  1. Navegador do Usuário                            │
│     └─ Extensão Chrome MV3                         │
│          ├─ content.js (captura DOM)                │
│          ├─ background.js (service worker HTTP)     │
│          └─ popup.html (login + status)             │
└──────────────────────┬──────────────────────────────┘
                       │ POST /api/audit/logs
                       │ Authorization: Bearer {token}
┌──────────────────────▼──────────────────────────────┐
│  2. API Laravel 12                                  │
│     ├─ Nginx :8000 (proxy HTTP)                    │
│     ├─ PHP-FPM                                     │
│     ├─ Autenticação (Sanctum / Sessão)             │
│     ├─ Validação e persistência                    │
│     └─ Despacho para fila Redis                    │
└──────────────────────┬──────────────────────────────┘
                       │ Redis LPUSH laravel_database_gemini
┌──────────────────────▼──────────────────────────────┐
│  3. MySQL 8.0                                       │
│     ├─ audit_logs                                  │
│     ├─ users                                       │
│     ├─ token_alerts / token_alert_events           │
│     ├─ ai_provider_settings                        │
│     ├─ activity_logs                               │
│     └─ personal_access_tokens / sessions           │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  4. Redis 7                                         │
│     ├─ Fila: gemini (jobs de análise)              │
│     ├─ Fila: default                               │
│     └─ Cache Laravel                               │
└──────────────────────┬──────────────────────────────┘
                       │ BRPOP
┌──────────────────────▼──────────────────────────────┐
│  5. Worker (queue_worker container)                 │
│     └─ ProcessAuditLog job                         │
│          └─ AiProviderManager                      │
└──────────────────────┬──────────────────────────────┘
                       │ HTTPS
┌──────────────────────▼──────────────────────────────┐
│  6. Provedor de IA Configurável                     │
│     ├─ GeminiClient (API Google)                   │
│     ├─ OpenAiCompatibleClient (Grok, DeepSeek,     │
│     │   Customizado)                               │
│     └─ Fallback: GEMINI_API_KEY do .env            │
└─────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────┐
│  7. Painel Web (Blade + Vite + Tailwind)            │
│     ├─ Visão Geral                                 │
│     ├─ Auditoria (dados sempre mascarados)         │
│     ├─ Tokens (ranking + heatmap)                  │
│     ├─ Gestão de Usuários                          │
│     └─ Configurações do GovCert                    │
└─────────────────────────────────────────────────────┘
```

## Fluxo de Dados Detalhado

### Captura e ingestão

```
1. Usuário envia prompt na plataforma de IA
2. content.js detecta o envio e captura o texto
3. Quando a resposta aparece, content.js captura o output
4. Mensagem enviada ao background.js via postMessage
5. background.js lê api_url e token do chrome.storage.local
6. background.js → POST /api/audit/logs
7. AuditController valida Bearer Token (Sanctum)
8. AuditController valida is_active do usuário
9. AuditController cria audit_logs com status=pending
10. AuditController → TokenCounter.countPair() → preenche tokens estimados
11. AuditController → ProcessAuditLog::dispatch(log_id)->onQueue('gemini')
12. API retorna 202 Accepted {success: true, log_id: N}
13. Extensão segue sem aguardar análise
```

### Processamento assíncrono

```
1. queue_worker consome fila 'gemini'
2. ProcessAuditLog::handle() busca audit_log pelo id
3. Ignora se status já é 'completed' ou 'failed' (idempotência)
4. Atualiza status para 'in_analysis' e preenche tokens estimados
5. AiProviderManager::activeClient() → busca AiProviderSetting ativo
   └─ Se não existe config no banco: usa GeminiClient com GEMINI_API_KEY do .env
6. Cliente de IA chama o provedor configurado
7. Se o provedor retorna usageMetadata: usa tokens reais (method=gemini_api)
8. Se não: usa estimativa local (method=estimated)
9. Atualiza: status=completed, risk_level, leak_type, has_sensitive_data,
            gemini_justification, gemini_raw_response, tokens
10. Em caso de erro: relança exceção → Laravel agenda retry
11. Após 3 tentativas: failed() → status=failed, error_reason
```

### Visualização no painel

```
1. Auditor/admin abre /audit/logs
2. AuditLogController retorna logs paginados (sem input_text/output_text no HTML)
3. Clique em um log → fetch GET /audit/logs/{id}/detail
4. AuditLogDetailController::show() busca o log
5. SensitiveDataMasker::mask(input_text) → substitui dados sensíveis
6. SensitiveDataMasker::mask(output_text) → substitui dados sensíveis
7. Retorna JSON com input_masked, output_masked, tokens
8. Frontend exibe versão mascarada — NUNCA o texto original
9. Não existe endpoint para revelar o original
```

## Containers Docker

| Container | Imagem | Responsabilidade |
|---|---|---|
| `app` | `laravel-app` (customizada) | PHP-FPM 8.2, todas as dependências |
| `webserver` | `nginx:alpine` | Proxy HTTP, serve arquivos estáticos |
| `db` | `mysql:8.0` | Persistência principal |
| `redis` | `redis:7-alpine` | Filas, cache |
| `queue_worker` | `laravel-app` | Consome fila gemini, executa análise |
| `scheduler` | `laravel-app` | Executa `schedule:work` |
| `phpmyadmin` | `phpmyadmin` | Interface web do MySQL (perfil dev) |
| `mailpit` | `axllent/mailpit` | Captura SMTP em desenvolvimento (perfil dev) |

## Camada de Provedor de IA

O sistema usa uma abstração `AiProviderClient` que permite trocar o provedor sem alterar o job:

```
AiProviderManager::activeClient()
  ├─ Consulta ai_provider_settings WHERE is_active = true
  ├─ Se encontrado e com chave → instancia cliente específico:
  │   ├─ provider=gemini   → GeminiClient
  │   ├─ provider=grok     → OpenAiCompatibleClient (https://api.x.ai/v1)
  │   ├─ provider=deepseek → OpenAiCompatibleClient (https://api.deepseek.com/v1)
  │   └─ provider=custom   → OpenAiCompatibleClient (base_url configurável)
  └─ Se não encontrado → GeminiClient com config('services.gemini.api_key')
```

Ambos implementam a interface `AiProviderClient`:
- `testConnection(): AiProviderTestResult`
- `analyzeAuditLog(AuditLog $log): array`

## Decisões Arquiteturais

### Por que usar fila?

A análise com IA pode levar de 1 a 10 segundos. A extensão não pode esperar esse tempo — a experiência do usuário seria degradada e requisições longas poderiam gerar timeout. A fila permite resposta imediata (202) e processamento em background.

### Por que `captured_at` é definido pelo servidor?

O relógio do cliente (navegador/extensão) pode ser adulterado. Em sistemas de auditoria, a fonte de verdade do timestamp deve ser sempre o servidor.

### Por que mascarar na visualização e não no banco?

O banco é a evidência forense. Se um incidente for investigado judicialmente, o dado original precisa existir em algum lugar auditável. A interface oculta o dado para proteger a privacidade no uso cotidiano; o banco preserva para investigações formais com controles de acesso adequados.

### Por que dados nunca são revelados na interface?

Mesmo admins e auditores veem dados mascarados. Isso evita:
1. Vazamento via print de tela do painel
2. Acesso acidental a dados sensíveis durante revisão rotineira
3. Ampliação desnecessária da superfície de exposição

### Por que perfil `usuario`?

Servidores comuns monitorados pela extensão não precisam de acesso ao painel. Criar um perfil específico para eles evita que, por acidente, um servidor tenha acesso a logs e relatórios de colegas.

### Por que Manifest V3?

MV3 é o padrão atual do Chrome. Extensões MV2 estão sendo descontinuadas. A extensão usa service worker (`background.js`) conforme exigido pelo MV3.

### Por que Redis para a fila?

Redis oferece baixa latência, persistência configurável e é amplamente suportado. Laravel Queue com Redis é uma combinação madura e confiável para workloads de processamento assíncrono.

## Relacionados

- [[00 - Visão Geral]]
- [[07 - Módulos e Componentes]]
- [[08 - Banco de Dados]]
- [[12 - Extensão Chrome]]
- [[15 - Configurações do GovCert]]
- [[20 - Decisões Técnicas]]
