# 07 - API E Rotas

## Base URL

Local:

```text
http://localhost:8000
```

Producao:

```text
https://seu-dominio.gov.br
```

## API Publica

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `POST` | `/api/register` | Cadastra usuario |
| `POST` | `/api/login` | Login API generico |
| `POST` | `/api/extension/login` | Login da extensao |
| `POST` | `/api/forgot-password` | Solicita reset |
| `POST` | `/api/reset-password` | Redefine senha |

## API Protegida

Header obrigatorio:

```http
Authorization: Bearer {token}
```

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `GET` | `/api/user` | Usuario autenticado |
| `POST` | `/api/logout` | Logout API |
| `POST` | `/api/extension/logout` | Logout extensao |
| `POST` | `/api/audit/logs` | Ingestao de log |

## Exemplo De Login Da Extensao

```bash
curl -X POST http://localhost:8000/api/extension/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@govcert.gov.br","password":"admin123","device_name":"chrome-dev"}'
```

Resposta esperada:

```json
{
  "token": "1|token...",
  "user_name": "Admin",
  "user_email": "admin@govcert.gov.br"
}
```

## Exemplo De Envio De Log

```bash
curl -X POST http://localhost:8000/api/audit/logs \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "user_identifier": "admin@govcert.gov.br",
    "input_text": "Texto enviado pelo usuario",
    "output_text": "Resposta da IA",
    "url_source": "https://chatgpt.com/c/teste"
  }'
```

Resposta:

```json
{
  "success": true,
  "log_id": 42
}
```

Status HTTP: `202 Accepted`.

## Rotas Web Publicas

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `GET` | `/login` | Formulario de login |
| `POST` | `/login` | Processa login |
| `GET` | `/reset-password/{token}` | Formulario de reset |
| `POST` | `/reset-password` | Processa reset |

## Rotas Web Autenticadas

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `GET` | `/` | Dashboard |
| `GET` | `/audit/logs` | Listagem |
| `GET` | `/audit/logs/export/csv` | Export CSV |
| `POST` | `/audit/logs/export/pdf` | Export PDF |
| `GET` | `/profile` | Perfil |
| `PUT` | `/profile` | Atualizar perfil |
| `PUT` | `/profile/password` | Alterar senha |
| `GET` | `/profile/api-tokens` | Tokens |
| `POST` | `/profile/api-tokens` | Criar token |
| `DELETE` | `/profile/api-tokens` | Revogar token |
| `POST` | `/logout` | Logout |

## Rotas Admin

| Metodo | Rota | Descricao |
|--------|------|-----------|
| `GET` | `/admin/users` | Usuarios |
| `PUT` | `/admin/users/{user}` | Editar usuario |
| `POST` | `/admin/users/{user}/toggle` | Ativar/desativar |
| `POST` | `/admin/users/{user}/reset-link` | Enviar reset |
| `GET` | `/admin/activity` | Activity log |
