# 02 - Arquitetura

## Visao Geral

O GovCert usa uma arquitetura em camadas:

```text
Navegador
  └─ Extensao Chrome
       └─ API Laravel
            ├─ MySQL
            ├─ Redis
            └─ Worker
                 └─ Google Gemini API
```

A extensao captura interacoes e envia para a API. A API persiste o log e despacha um job. O worker consome a fila, chama o Gemini e atualiza o registro com a classificacao.

## Componentes

| Componente | Tecnologia | Responsabilidade |
|------------|------------|------------------|
| Extensao | Chrome MV3, JavaScript vanilla | Captura input/output e envia logs |
| API | Laravel 12 | Autenticacao, validacao, persistencia e painel |
| Worker | Laravel Queue | Analise assincrona com Gemini |
| Banco | MySQL 8.0 | Persistencia relacional |
| Fila/cache | Redis 7 | Jobs, cache e suporte operacional |
| Proxy | Nginx | Entrada HTTP para PHP-FPM |

## Containers

| Container | Funcao |
|-----------|--------|
| `app` | PHP-FPM com aplicacao Laravel |
| `webserver` | Nginx escutando `localhost:8000` |
| `db` | MySQL |
| `redis` | Redis |
| `queue_worker` | `php artisan queue:work` |
| `scheduler` | `php artisan schedule:work` |
| `phpmyadmin` | Ferramenta opcional de desenvolvimento |
| `mailpit` | Captura de e-mails em desenvolvimento |

## Comunicacao

| Origem | Destino | Protocolo | Autenticacao |
|--------|---------|-----------|--------------|
| Extensao | `/api/extension/login` | HTTP JSON | E-mail e senha |
| Extensao | `/api/audit/logs` | HTTP JSON | Bearer Token Sanctum |
| Laravel | Redis | Redis protocol | Rede interna Docker |
| Worker | MySQL | TCP/SQL | Credenciais do `.env` |
| Worker | Gemini | HTTPS | `GEMINI_API_KEY` |
| Navegador | Painel web | HTTP | Sessao Laravel |

## Fluxo de Dados

```text
content.js
  -> background.js
    -> POST /api/audit/logs
      -> AuditController
        -> audit_logs(status=pending)
          -> Redis queue(gemini)
            -> ProcessAuditLog
              -> Gemini API
                -> audit_logs(status=completed/failed)
```

## Responsabilidades Por Camada

### Extensao Chrome

- Detecta plataforma suportada.
- Observa elementos do DOM.
- Captura texto enviado e resposta gerada.
- Armazena token e URL da API no storage local.
- Envia logs autenticados para o backend.

### Backend Laravel

- Valida requests.
- Autentica tokens da API e sessoes web.
- Persiste logs e usuarios.
- Gera dashboard e exports.
- Despacha jobs para fila.
- Registra eventos no `ActivityLog`.

### Worker

- Consome fila `gemini`.
- Evita reprocessar logs em status terminal.
- Chama API externa do Gemini.
- Atualiza classificacao e justificativa.
- Marca falhas definitivas com `error_reason`.

## Decisoes Arquiteturais

| Decisao | Justificativa |
|---------|---------------|
| Fila para Gemini | Evita travar a requisicao da extensao |
| `captured_at` no servidor | Evita confiar no relogio do cliente |
| Manifest V3 | Compatibilidade com Chrome moderno |
| JavaScript vanilla na extensao | Instalacao direta, sem build |
| Redis para fila | Melhor para jobs assincronos que banco |
| Sessao web separada de token API | Contextos de uso diferentes |
