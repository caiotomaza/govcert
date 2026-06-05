# 03 - Instalacao e Configuracao

## Pre-Requisitos

- Docker e Docker Compose.
- Git, caso va clonar ou atualizar o repositorio.
- Chave da Google Gemini API.

PHP, Composer, Node, MySQL e Redis rodam em containers.

## Setup Local

Crie o `.env`:

```bash
cp src/.env.example src/.env
```

Preencha:

```dotenv
GEMINI_API_KEY=sua_chave_aqui
```

Suba o ambiente:

```bash
docker compose build app
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

Acesse:

```text
http://localhost:8000
```

## Ferramentas De Desenvolvimento

Subir phpMyAdmin e Mailpit:

```bash
docker compose --profile dev up -d
```

URLs:

| Ferramenta | URL |
|------------|-----|
| Painel | `http://localhost:8000` |
| phpMyAdmin | `http://localhost:8080` |
| Mailpit | `http://localhost:8025` |

## Variaveis Principais

| Variavel | Descricao |
|----------|-----------|
| `APP_KEY` | Chave de criptografia Laravel |
| `APP_ENV` | Ambiente atual |
| `APP_DEBUG` | Debug na tela, deve ser `false` em producao |
| `APP_URL` | URL base do sistema |
| `GEMINI_API_KEY` | Chave para chamar Gemini |
| `DB_HOST` | Host do MySQL |
| `DB_DATABASE` | Nome do banco |
| `DB_USERNAME` | Usuario do banco |
| `DB_PASSWORD` | Senha do banco |
| `QUEUE_CONNECTION` | Deve ser `redis` |
| `CACHE_STORE` | Preferencialmente `redis` |
| `SESSION_DRIVER` | `database` no ambiente padrao |
| `MAIL_HOST` | SMTP ou `mailpit` local |

## Credenciais Seed

| Usuario | E-mail | Senha | Role |
|---------|--------|-------|------|
| Admin | `admin@govcert.gov.br` | `admin123` | `admin` |
| Chefe | `chefe@govcert.gov.br` | `admin123` | `admin` |
| Auditor | `auditor@govcert.gov.br` | `auditor123` | `auditor` |

## Validacao Inicial

1. Acesse `/login`.
2. Entre com `admin@govcert.gov.br`.
3. Confira o dashboard.
4. Abra `/audit/logs`.
5. Verifique o worker:

```bash
docker compose logs -f queue_worker
```

6. Teste login da extensao:

```bash
curl -X POST http://localhost:8000/api/extension/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@govcert.gov.br","password":"admin123","device_name":"teste"}'
```
