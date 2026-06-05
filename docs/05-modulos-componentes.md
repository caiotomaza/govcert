# 05 - Modulos e Componentes

## Job `ProcessAuditLog`

Local:

```text
src/app/Jobs/ProcessAuditLog.php
```

Responsavel pela integracao com Gemini.

Caracteristicas:

- recebe ID do log;
- busca `AuditLog`;
- evita reprocessar status terminal;
- altera status para `in_analysis`;
- chama Gemini;
- grava classificacao;
- em falha final, grava `error_reason`.

## Controllers API

### `AuditController`

Local:

```text
src/app/Http/Controllers/Api/AuditController.php
```

Recebe logs da extensao em `POST /api/audit/logs`.

### `ExtensionAuthController`

Local:

```text
src/app/Http/Controllers/Api/ExtensionAuthController.php
```

Faz login/logout da extensao.

### `AuthController`

Local:

```text
src/app/Http/Controllers/Api/AuthController.php
```

Fornece autenticacao API generica, usuario autenticado e logout.

### `PasswordResetController`

Local:

```text
src/app/Http/Controllers/Api/PasswordResetController.php
```

Fluxo API de esqueci senha e reset.

## Controllers Web

| Controller | Responsabilidade |
|------------|------------------|
| `DashboardController` | KPIs, graficos e visao geral |
| `AuditLogController` | Listagem, filtros, CSV e PDF |
| `ProfileController` | Perfil e senha |
| `ApiTokenController` | Tokens da extensao |
| `AuthController` | Login e logout web |
| `PasswordResetController` | Reset de senha via pagina |

## Controllers Admin

| Controller | Responsabilidade |
|------------|------------------|
| `UserController` | Editar usuarios, status, roles e reset |
| `ActivityLogController` | Listar trilha de atividades |

## Middlewares

| Middleware | Alias | Funcao |
|------------|-------|-------|
| `CheckUserStatus` | `active` | Bloqueia usuario inativo no painel |
| `IsAdmin` | `admin` | Restringe rotas administrativas |

## Models

### `User`

Representa usuarios do sistema, roles, status e tokens Sanctum.

### `AuditLog`

Modelo central de logs capturados. Possui accessors para cor de risco e nome da plataforma.

### `ActivityLog`

Trilha de auditoria de acoes do sistema. O metodo `record()` tenta registrar a acao sem quebrar o fluxo principal em caso de erro.

## Views

| Pasta | Conteudo |
|-------|----------|
| `resources/views/layouts` | Layout base |
| `resources/views/dashboard` | Dashboard |
| `resources/views/audit` | Listagem, tabela parcial e PDF |
| `resources/views/profile` | Perfil e tokens |
| `resources/views/admin` | Usuarios e activity log |
| `resources/views/components` | Botao, modal, navbar e badges |
