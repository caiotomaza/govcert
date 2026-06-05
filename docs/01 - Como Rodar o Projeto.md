# 01 - Como Rodar o Projeto

> **Pré-requisito único**: Docker e Docker Compose instalados. PHP, Composer, Node e MySQL **não** precisam estar instalados localmente — tudo roda dentro dos containers.

---

## Instalação Completa

### 1. Clonar o repositório

```bash
git clone <url-do-repositorio> govcert
cd govcert
```

### 2. Configurar o ambiente

```bash
cp src/.env.example src/.env
```

Abra `src/.env` e configure no mínimo:

```dotenv
# Obrigatório após key:generate:
APP_KEY=

# Opcional — pode configurar pelo painel depois:
GEMINI_API_KEY=sua_chave_aqui
```

As demais variáveis já têm valores adequados para desenvolvimento local. Consulte [[05 - Configuração de Ambiente]] para detalhes.

### 3. Build da imagem PHP

```bash
docker compose build app
```

### 4. Subir os containers

```bash
# Apenas serviços essenciais:
docker compose up -d

# Com phpMyAdmin (porta 8080) e Mailpit (porta 8025):
docker compose --profile dev up -d
```

### 5. Instalar dependências PHP

```bash
docker compose exec app composer install
```

### 6. Gerar chave da aplicação

```bash
docker compose exec app php artisan key:generate --force
```

### 7. Instalar dependências Node e compilar assets

```bash
docker compose exec app npm install
docker compose exec app npm run build
```

Para desenvolvimento com hot-reload:

```bash
docker compose exec app npm run dev
```

### 8. Rodar migrations

```bash
docker compose exec app php artisan migrate --force
```

### 9. Popular dados de desenvolvimento

```bash
docker compose exec app php artisan db:seed --force
```

Cria automaticamente:
- 2 administradores, 1 auditor, 8 usuários comuns, 1 usuário inativo
- ~425 logs de auditoria com dados fictícios
- Alertas de tokens
- Configuração inicial de provedor de IA

### 10. Corrigir permissões de storage

```bash
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

### 11. Acessar o sistema

Abra `http://localhost:8000` no navegador. Deve redirecionar para `/login`.

---

## Setup Automatizado (alternativa)

```bash
docker compose exec app composer setup
```

Este script executa: `composer install` → `key:generate` → `migrate` → `npm install` → `npm run build`.

**Não** cobre: seed, permissões de storage, configuração do `.env`.

---

## Credenciais de Desenvolvimento

| Perfil | E-mail | Senha |
|---|---|---|
| Admin | `admin@govcert.gov.br` | `admin123` |
| Chefe Admin | `chefe@govcert.gov.br` | `admin123` |
| Auditor | `auditor@govcert.gov.br` | `auditor123` |
| Usuário extensão | `maria.silva@govcert.gov.br` | `usuario123` |
| Usuário inativo | `bloqueado@govcert.gov.br` | `usuario123` |

---

## Verificações Pós-Instalação

### Verificar telas principais

1. `http://localhost:8000` → redireciona para `/login` ✓
2. Login com `admin@govcert.gov.br / admin123` → abre Visão Geral ✓
3. Menu → **Auditoria** → deve listar logs ✓
4. Clicar num log → modal abre com dados mascarados ✓
5. Menu → **Tokens** → deve mostrar ranking ✓

### Testar o provedor de IA

1. Login como admin.
2. Dropdown do nome → **Configurações do GovCert**.
3. Preencha a chave da API no campo "Chave de API".
4. Campo "Modelo": `gemini-1.5-flash` (ou o modelo desejado).
5. Clique em **Testar API**.
6. Deve retornar "Conexão bem-sucedida" com tempo de resposta.

### Testar envio de e-mail

Com Mailpit ativo:

1. Login como admin → `/admin/users`.
2. Clique em **+ Cadastrar Usuário**.
3. Preencha um e-mail qualquer e clique em **Cadastrar**.
4. Acesse `http://localhost:8025` → o e-mail deve aparecer.

### Testar extensão Chrome

1. Abra `chrome://extensions`.
2. Ative o **Modo desenvolvedor**.
3. Clique em **Carregar sem compactação**.
4. Selecione a pasta `chrome-extension/`.
5. Clique no ícone da extensão → informe `http://localhost:8000` como URL da API.
6. Faça login com `maria.silva@govcert.gov.br / usuario123`.
7. Acesse ChatGPT ou Claude e envie um prompt.
8. Verifique o log em `http://localhost:8000/audit/logs`.

---

## Portas e Serviços

| Serviço | Porta | URL |
|---|---|---|
| Painel web | 8000 | `http://localhost:8000` |
| MySQL | 3306 | Direto via cliente SQL |
| Redis | 6379 | Direto via `redis-cli` |
| phpMyAdmin | 8080 | `http://localhost:8080` (perfil dev) |
| Mailpit | 8025 | `http://localhost:8025` (perfil dev) |

---

## Comandos Úteis no Dia a Dia

```bash
# Ver status dos containers
docker compose ps

# Logs em tempo real do worker
docker compose logs -f queue_worker

# Logs da aplicação Laravel
docker compose exec app php artisan pail

# Rodar testes
docker compose exec app php artisan test

# Limpar todos os caches
docker compose exec app php artisan optimize:clear

# Reset completo do banco (somente desenvolvimento)
docker compose exec app php artisan migrate:fresh --seed
```

---

## Relacionados

- [[00 - Visão Geral]]
- [[05 - Configuração de Ambiente]]
- [[12 - Extensão Chrome]]
- [[16 - Seeds e Dados de Teste]]
- [[21 - Troubleshooting]]
