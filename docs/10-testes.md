# 10 - Testes

## Framework

O projeto usa PHPUnit via Laravel:

```bash
docker compose exec app php artisan test
```

## Comandos

Todos os testes:

```bash
docker compose exec app php artisan test
```

Por classe:

```bash
docker compose exec app php artisan test --filter=ApiAuthTest
```

Por metodo:

```bash
docker compose exec app php artisan test --filter=ApiAuthTest::test_user_can_login
```

Via Composer:

```bash
docker compose exec app composer test
```

## Estrutura

```text
src/tests/
├── Feature/
│   ├── ApiAuthTest.php
│   ├── ApiTokenTest.php
│   ├── AuditIngestionTest.php
│   ├── DashboardAccessTest.php
│   ├── ProcessAuditLogJobTest.php
│   └── UserManagementTest.php
└── Unit/
    └── AuditLogTest.php
```

## Cobertura Atual

| Area | Cobertura |
|------|-----------|
| API auth | Registro, login, logout e usuario autenticado |
| Tokens | Criacao, revogacao e token unico |
| Ingestao | Request valido, sem auth e validacao |
| Dashboard | Acesso por role e usuario inativo |
| Job | Sucesso, falha e idempotencia |
| Usuarios | Edicao, status e reset |
| AuditLog | Accessors de risco e plataforma |

## Lacunas

- Extensao Chrome sem testes automatizados.
- Exportacao CSV/PDF precisa de cobertura dedicada.
- Filtros combinados de logs merecem testes.
- Resposta malformada do Gemini deve ser testada.
- Usuario inativo em rotas API precisa de regra e cobertura.

## Antes De Alterar Areas Criticas

Para `ProcessAuditLog`:

```bash
docker compose exec app php artisan test --filter=ProcessAuditLogJobTest
```

Para autenticacao:

```bash
docker compose exec app php artisan test --filter=ApiAuthTest
docker compose exec app php artisan test --filter=DashboardAccessTest
```
