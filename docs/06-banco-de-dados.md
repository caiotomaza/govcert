# 06 - Banco De Dados

## Tecnologia

O GovCert usa MySQL 8.0 com Eloquent ORM. As migrations ficam em:

```text
src/database/migrations/
```

## Tabelas Principais

### `users`

Usuarios do painel e da extensao.

| Campo | Descricao |
|-------|-----------|
| `id` | Chave primaria |
| `name` | Nome |
| `email` | E-mail unico |
| `password` | Hash da senha |
| `role` | `admin` ou `auditor` |
| `is_active` | Conta ativa ou bloqueada |
| `remember_token` | Token de lembrar sessao |
| `created_at`, `updated_at` | Timestamps |

### `audit_logs`

Tabela central do sistema.

| Campo | Descricao |
|-------|-----------|
| `user_id` | Usuario relacionado, nullable |
| `user_identifier` | Identificador enviado pela extensao |
| `input_text` | Texto enviado para IA |
| `output_text` | Resposta da IA |
| `url_source` | URL da plataforma |
| `captured_at` | Data definida pelo servidor |
| `status` | Estado do processamento |
| `error_reason` | Motivo de falha |
| `has_sensitive_data` | Indicador de dado sensivel |
| `risk_level` | Nivel de risco |
| `leak_type` | Tipo de vazamento |
| `gemini_justification` | Justificativa da analise |
| `gemini_raw_response` | Resposta bruta do Gemini |
| `processed_at` | Data de conclusao |

Status possiveis:

- `pending`
- `in_analysis`
- `completed`
- `failed`

Riscos:

- `low`
- `medium`
- `high`
- `critical`

### `activity_logs`

Registra acoes administrativas e eventos relevantes.

| Campo | Descricao |
|-------|-----------|
| `user_id` | Usuario executor |
| `action` | Codigo da acao |
| `description` | Texto legivel |
| `subject_type` | Classe do alvo |
| `subject_id` | ID do alvo |
| `ip_address` | IP |
| `user_agent` | Navegador |
| `properties` | JSON adicional |
| `created_at` | Timestamp |

### `personal_access_tokens`

Tabela do Sanctum. Armazena hash dos tokens Bearer.

### `sessions`

Sessoes web quando `SESSION_DRIVER=database`.

## Relacionamentos

```text
users 1 -> N audit_logs
users 1 -> N activity_logs
users 1 -> N personal_access_tokens
activity_logs -> subject polimorfico
```

## Comandos Uteis

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan migrate:status
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan migrate:fresh --seed
```

## Cuidados

- `migrate:fresh` apaga todos os dados.
- `gemini_raw_response` pode conter dados sensiveis.
- Logs crescem continuamente se nao houver politica de retencao.
- Deletar usuario nao apaga logs automaticamente.
