# GovCert — Runbook Operacional

Documento de operação do GovCert. Para visão geral do sistema, consulte [README.md](README.md). Para documentação técnica completa, consulte [docs/](docs/README.md).

---

## Índice

- [Resumo dos Containers](#resumo-dos-containers)
- [Setup Inicial](#setup-inicial)
- [Credenciais de Desenvolvimento](#credenciais-de-desenvolvimento)
- [Comandos do Dia a Dia](#comandos-do-dia-a-dia)
- [Operação de Filas](#operação-de-filas)
- [Provedor de IA](#provedor-de-ia)
- [Mascaramento de Dados](#mascaramento-de-dados)
- [Tokens e Alertas](#tokens-e-alertas)
- [Gestão de Usuários](#gestão-de-usuários)
- [Logout All](#logout-all)
- [Build e Deploy](#build-e-deploy)
- [Troubleshooting](#troubleshooting)
- [Rotinas Recomendadas](#rotinas-recomendadas)
- [Checklist de Produção](#checklist-de-produção)

---

## Resumo dos Containers

| Container | Porta host | Função |
|---|---|---|
| `app` | — | PHP 8.2-FPM com Laravel |
| `webserver` | `8000` | Nginx, proxy para PHP-FPM |
| `db` | `3306` | MySQL 8.0 |
| `redis` | `6379` | Redis para filas e cache |
| `queue_worker` | — | Worker de análise com IA |
| `scheduler` | — | `php artisan schedule:work` |
| `phpmyadmin` | `8080` | Interface MySQL (perfil `dev`) |
| `mailpit` | `8025` | Captura de e-mails (perfil `dev`) |

O worker usa:

```bash
php artisan queue:work redis --queue=gemini,default --sleep=3 --tries=3 --max-time=3600 --max-jobs=500
```

---

## Setup Inicial

### 1. Copiar e configurar ambiente

```bash
cp src/.env.example src/.env
```

Edite `src/.env` e preencha no mínimo:

```dotenv
APP_KEY=          # gerado no passo 6
GEMINI_API_KEY=   # opcional se configurar pelo painel depois
```

### 2. Build da imagem PHP

```bash
docker compose build app
```

### 3. Subir containers

```bash
docker compose up -d

# Com phpMyAdmin e Mailpit (desenvolvimento):
docker compose --profile dev up -d
```

### 4. Instalar dependências PHP

```bash
docker compose exec app composer install
```

### 5. Instalar dependências Node e compilar assets

```bash
docker compose exec app npm install
docker compose exec app npm run build
# Em desenvolvimento (watch):
# docker compose exec app npm run dev
```

### 6. Gerar chave da aplicação

```bash
docker compose exec app php artisan key:generate --force
```

### 7. Rodar migrations

```bash
docker compose exec app php artisan migrate --force
```

### 8. Popular dados de desenvolvimento

```bash
docker compose exec app php artisan db:seed --force
```

O seed cria 12 usuários, ~425 logs realistas, alertas de tokens e configurações iniciais.

### 9. Corrigir permissões de storage

```bash
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

### 10. Validar

```bash
# Abrir no navegador:
open http://localhost:8000
# Deve redirecionar para /login
```

### Setup automatizado (alternativa)

```bash
docker compose exec app composer setup
```

Este script executa: install → key:generate → migrate → npm install → npm run build. **Não** roda seed nem corrige permissões automaticamente.

---

## Credenciais de Desenvolvimento

Após `db:seed --force`:

| Perfil | E-mail | Senha | Acesso |
|---|---|---|---|
| Admin principal | `admin@govcert.gov.br` | `admin123` | Painel completo |
| Chefe de Segurança | `chefe@govcert.gov.br` | `admin123` | Painel completo |
| Auditor | `auditor@govcert.gov.br` | `auditor123` | Painel (sem admin) |
| Usuário da extensão | `maria.silva@govcert.gov.br` | `usuario123` | Somente extensão/API |
| Usuário inativo | `bloqueado@govcert.gov.br` | `usuario123` | Bloqueado |

> **Atenção**: troque estas credenciais antes de qualquer ambiente real.

### Perfis de acesso resumidos

- **admin**: acessa tudo, incluindo Gestão de Usuários e Configurações do GovCert.
- **auditor**: acessa Visão Geral, Auditoria, Tokens e Perfil.
- **usuario**: não acessa o painel web. Só autentica na extensão Chrome e envia logs via API.

---

## Comandos do Dia a Dia

### Containers

```bash
docker compose ps
docker compose up -d
docker compose down
docker compose restart app
docker compose logs -f app
docker compose logs -f queue_worker
```

### Laravel

```bash
docker compose exec app php artisan optimize:clear      # limpar todos os caches
docker compose exec app php artisan config:clear
docker compose exec app php artisan route:clear
docker compose exec app php artisan view:clear
docker compose exec app php artisan config:cache        # reconstruir cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
```

### Banco

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate:fresh --seed  # APENAS em desenvolvimento
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan tinker
```

### Testes

```bash
# Todos os testes:
docker compose exec app php artisan test

# Por classe:
docker compose exec app php artisan test --filter=SensitiveDataMaskerTest
docker compose exec app php artisan test --filter=TokenGovernanceTest
docker compose exec app php artisan test --filter=RoleUsuarioTest

# Por método:
docker compose exec app php artisan test --filter=test_auditor_ve_conteudo_mascarado_no_detalhe

# Via Composer (limpa config antes):
docker compose exec app composer test
```

### Pint (formatação de código)

```bash
docker compose exec app ./vendor/bin/pint
```

### Assets frontend

```bash
docker compose exec app npm run build    # produção
docker compose exec app npm run dev      # watch (desenvolvimento)
```

### Logs

```bash
docker compose exec app tail -f storage/logs/laravel.log
docker compose exec app php artisan pail                    # tail interativo
```

---

## Operação de Filas

### Status

```bash
docker compose ps queue_worker
docker compose logs -f queue_worker
docker compose exec redis redis-cli LLEN laravel_database_gemini
```

### Jobs com falha

```bash
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
docker compose exec app php artisan queue:flush        # descarta todos os failed
```

### Reiniciar worker (obrigatório após deploy)

```bash
docker compose exec app php artisan queue:restart
```

### Logs presos em `pending`

Causa: worker parado ou Redis indisponível.

```bash
docker compose ps queue_worker redis
docker compose restart queue_worker redis
docker compose exec app php artisan queue:restart
```

### Logs presos em `in_analysis`

O worker travou durante o processamento. Verifique:

```bash
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

Se nenhum job estiver na fila mas os logs ficaram presos:

```bash
docker compose exec app php artisan tinker
```

```php
AuditLog::where('status', 'in_analysis')->update(['status' => 'pending']);
```

Depois reenvie/recrie os jobs conforme necessário.

---

## Provedor de IA

### Configuração pelo painel

1. Acesse `http://localhost:8000` como admin.
2. No dropdown do nome do usuário → **Configurações do GovCert**.
3. Selecione o provedor (Gemini, Grok, DeepSeek, Customizado).
4. Informe o modelo, base URL (quando necessário) e a chave da API.
5. Clique em **Testar API** para validar antes de salvar.
6. Clique em **Salvar Configuração**.

O worker passará a usar o provedor ativo na próxima execução.

### Fallback para `.env`

Se nenhuma configuração ativa existir no banco, o sistema usa `GEMINI_API_KEY` do `.env` com o modelo `gemini-1.5-flash`. Isso garante compatibilidade com instalações antigas.

### Provedores suportados

| Provedor | Base URL padrão | Protocolo |
|---|---|---|
| Gemini | `generativelanguage.googleapis.com` | API própria Google |
| Grok (xAI) | `https://api.x.ai/v1` | OpenAI-compatível |
| DeepSeek | `https://api.deepseek.com/v1` | OpenAI-compatível |
| Customizado | Configurável | OpenAI-compatível |

### Validar chave carregada

```bash
docker compose exec app php artisan tinker --execute="
  \$s = App\Models\AiProviderSetting::active();
  echo \$s ? 'Ativo: '.\$s->provider.' ('.\$s->model.')' : 'Usando .env fallback';
"
```

### Erros comuns do Gemini

- `400 INVALID_ARGUMENT` → modelo inválido (ex.: campo "Modelo" com texto errado).
- `403 API_KEY_INVALID` → chave incorreta ou expirada.
- `429 RESOURCE_EXHAUSTED` → quota excedida.
- `503` → instabilidade da API Google.

Após corrigir `.env` ou a configuração do painel:

```bash
docker compose exec app php artisan config:clear
docker compose restart queue_worker
docker compose exec app php artisan queue:retry all
```

### Segurança da API key

- A chave **não** é exibida completa no painel (mostra `AIza****wxyz`).
- No banco fica criptografada com `encrypt()` do Laravel.
- **Nunca** aparece em logs Laravel nem no ActivityLog.

---

## Mascaramento de Dados

### Funcionamento

O `SensitiveDataMasker` substitui dados sensíveis por marcadores **antes** de qualquer dado ser enviado ao frontend. O texto original fica exclusivamente no banco de dados como evidência forense.

**Regra absoluta**: nenhum usuário, independente de perfil, consegue revelar o conteúdo original pela interface web. Não existe botão "Mostrar original".

### Dados detectados e seus marcadores

| Tipo | Marcador |
|---|---|
| CPF | `[CPF OCULTO]` |
| CNPJ | `[CNPJ OCULTO]` |
| E-mail | `[E-MAIL OCULTO]` |
| Telefone | `[TELEFONE OCULTO]` |
| Senha/credencial | `[CREDENCIAL OCULTA]` |
| Bearer token | `Bearer [TOKEN OCULTO]` |
| JWT | `[TOKEN OCULTO]` |
| API key / AWS key | `[CHAVE/API KEY OCULTA]` |
| URL com parâmetro sensível | `[DADO SENSÍVEL OCULTO]` |

### Como validar o mascaramento

1. Acesse `http://localhost:8000` como admin ou auditor.
2. Vá em **Auditoria**.
3. Clique em qualquer log da lista.
4. O modal deve exibir os dados com marcadores (ex.: `[CPF OCULTO]` em vez de `123.456.789-00`).
5. **Não deve existir botão "Mostrar original"** — se existir, é um bug.

### Verificar no banco (só para auditoria técnica)

```bash
docker compose exec db mysql -ugovcert_user -proot govcert_dev \
  -e "SELECT id, LEFT(input_text, 100) FROM audit_logs LIMIT 5;"
```

O banco deve conter o texto original. A interface deve exibir a versão mascarada.

---

## Tokens e Alertas

### Validar contagem de tokens

1. Acesse um log na tela **Auditoria** e clique para ver o detalhe.
2. O modal mostra:
   - **Tokens de Entrada** (input)
   - **Tokens de Saída** (output)
   - **Total deste Log**
   - **Origem**: `api` (retornado pelo provedor) ou `estimado` (calculado localmente)
3. **Não deve aparecer "Acumulado do Usuário"** no modal — isso é exibido apenas no ranking da aba Tokens.

### Validar ranking e heatmap

1. Acesse **Tokens** no menu.
2. A tabela deve mostrar colunas:
   - **Total de Tokens** com badge de intensidade (Mínimo/Baixo/Médio/Alto/Crítico) e barra colorida.
   - **Qtd. de Logs** com badge e barra idênticos, mas com intensidade calculada separadamente.
3. Os dois heatmaps são independentes: um usuário pode ter alta intensidade em tokens mas baixa em quantidade de logs (ex.: Carla Moura nos dados de seed).

### Validar alertas

1. Em **Tokens**, role até a seção de alertas.
2. Deve aparecer lista com alertas ativos, inativos e com data de último disparo.
3. O alerta de Pedro Costa deve aparecer como disparado (dados do seed).

### Forçar verificação de alertas

```bash
docker compose exec app php artisan tinker
```

```php
(new App\Services\TokenAlertService(
    new App\Services\TokenUsageService()
))->checkAll();
```

---

## Gestão de Usuários

### Cadastrar novo usuário

1. Acesse `/admin/users` como admin.
2. Clique em **+ Cadastrar Usuário**.
3. Preencha Nome, E-mail e Perfil (Administrador, Auditor ou **Usuário**).
4. Clique em **Cadastrar**.
5. O sistema cria o usuário com senha aleatória e **envia automaticamente e-mail com link para criar a própria senha**.

> O admin **não** define a senha. O usuário recebe o link por e-mail.

### Perfil Usuário

O perfil **Usuário** serve exclusivamente para login na extensão Chrome. Se tentar acessar o painel, é redirecionado para `/login` com a mensagem: _"Seu perfil permite apenas o uso da extensão GovCert. O acesso ao painel é restrito a auditores e administradores."_

### Enviar link de acesso manualmente

Na lista de usuários, clique em **Enviar Link de Acesso** na linha do usuário desejado. Usa o mesmo fluxo do Password Broker do Laravel.

### Testar e-mail de criação de senha (desenvolvimento)

1. Suba Mailpit: `docker compose --profile dev up -d mailpit`
2. Cadastre um usuário pelo painel.
3. Acesse `http://localhost:8025` para ver o e-mail.

---

## Logout All

### Efeito esperado

O botão **Desconectar todos os usuários** (em Configurações do GovCert):

1. Registra no `ActivityLog` a ação com quantidade de sessões e tokens revogados.
2. Apaga todos os registros da tabela `sessions`.
3. Revoga todos os tokens Sanctum (`personal_access_tokens`).
4. Redireciona o próprio admin para `/login`.

**Todos os usuários do painel e da extensão precisarão autenticar novamente.**

### Recuperar acesso depois

Simplesmente faça login com `admin@govcert.gov.br / admin123` (ou as credenciais configuradas em produção).

Usuários da extensão precisarão fazer login novamente no popup da extensão.

---

## Build e Deploy

### Build de assets (frontend)

```bash
docker compose exec app npm run build
# Saída: src/public/build/
```

### Deploy inicial

```bash
docker compose build app
docker compose up -d
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan migrate --force
docker compose exec app npm run build
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

### Atualização de código

```bash
git pull origin main
docker compose build app
docker compose up -d
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php artisan migrate --force
docker compose exec app npm run build
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache
docker compose exec app php artisan queue:restart
```

### Configuração segura da API key em produção

**Nunca** salve a API key no código ou no repositório. Configure pelo painel (`/admin/settings`) após o deploy. Ela fica criptografada no banco.

---

## Troubleshooting

### `No application encryption key has been specified`

```bash
cp src/.env.example src/.env
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan config:clear
```

### `502 Bad Gateway`

```bash
docker compose ps
docker compose logs app
docker compose logs webserver
docker compose restart app webserver
```

### `Can't connect to MySQL server on 'db'`

MySQL ainda não terminou de subir. Aguarde e tente:

```bash
docker compose ps db
docker compose logs db
docker compose exec app php artisan migrate --force   # depois de alguns segundos
```

### `Table doesn't exist: sessions`

Migrations não rodaram:

```bash
docker compose exec app php artisan migrate --force
```

### Erro de permissão em `storage`

```bash
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

### Login admin falha com credenciais corretas

Seed não rodou:

```bash
docker compose exec app php artisan db:seed --force
```

### Extensão retorna 403

Usuário inativo. Admin deve reativar em `/admin/users`.

### Extensão retorna 401

- Token revogado ou inválido.
- URL da API incorreta.
- Usuário fez login novamente com mesmo `device_name`.

Solução: faça logout na extensão e refaça o login.

### Extensão não captura nada

Seletores CSS podem ter mudado. Abra DevTools na plataforma, inspecione os elementos e compare com `chrome-extension/content.js`. Atualize os seletores e recarregue a extensão em `chrome://extensions`.

### Usuário com perfil `usuario` não consegue acessar o painel

**Isso é comportamento esperado.** O perfil `usuario` só pode usar a extensão e a API. Ao tentar logar no painel, recebe a mensagem "Seu perfil permite apenas o uso da extensão GovCert."

### Dados sensíveis não aparecem mascarados no detalhe do log

Verifique se:
1. O log foi processado (status `completed`).
2. O `SensitiveDataMasker` está sendo chamado no `AuditLogDetailController::show()`.
3. O padrão regex cobre o tipo de dado (CPF, e-mail, etc.).

Rodar teste específico:

```bash
docker compose exec app php artisan test --filter=SensitiveDataMaskerTest
```

### Teste de API falha na tela de Configurações do GovCert

- Campo **Modelo** em branco ou com texto inválido (ex.: "Gemini API Key").
- Solução: coloque um nome de modelo válido (ex.: `gemini-1.5-flash`).
- Se a chave for inválida: salve uma nova chave pelo formulário.

### Token não contabilizado

Logs com `total_tokens = 0` indicam que o job não rodou ou falhou antes de contar. Verifique:

```bash
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
```

### Heatmap vazio na aba Tokens

Sem logs no período selecionado. Ajuste o filtro de datas ou rode o seed novamente:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

### Alertas não aparecem na aba Tokens

Verifique se existem registros em `token_alerts`:

```bash
docker compose exec app php artisan tinker --execute="echo App\Models\TokenAlert::count();"
```

Se for 0, rode o seed.

### E-mail de criação de senha não chega

Em desenvolvimento, o Mailpit captura todos os e-mails:

```bash
docker compose --profile dev up -d mailpit
# Acesse http://localhost:8025
```

Verifique `APP_URL` e `FRONTEND_URL` no `.env`:

```dotenv
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:8000
```

Depois:

```bash
docker compose exec app php artisan config:clear
```

### `Access denied for user 'govcert_user'`

Volume MySQL pode ter sido inicializado com outra configuração:

```bash
docker exec db mysql -uroot -proot -e "
  CREATE DATABASE IF NOT EXISTS govcert_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER IF NOT EXISTS 'govcert_user'@'%' IDENTIFIED BY 'root';
  GRANT ALL PRIVILEGES ON govcert_dev.* TO 'govcert_user'@'%';
  FLUSH PRIVILEGES;
"
docker compose exec app php artisan migrate --force
```

---

## Rotinas Recomendadas

### Diária

```bash
docker compose ps queue_worker
docker compose exec app php artisan queue:failed
```

- Verificar se o worker está em execução.
- Verificar se há jobs com falha.
- Checar logs críticos no painel (Visão Geral).

### Semanal

- Validar backups do banco.
- Revisar crescimento da tabela `audit_logs`.
- Verificar erros recorrentes em `storage/logs/laravel.log`.
- Revisar usuários inativos e tokens antigos.

### Após atualização de plataforma monitorada

1. Testar captura manualmente com a extensão.
2. Atualizar seletores em `chrome-extension/content.js` se necessário.
3. Recarregar extensão em `chrome://extensions`.
4. Enviar um log de teste e verificar em `/audit/logs`.

### Backup do banco

```bash
docker compose exec db mysqldump -ugovcert_user -proot govcert_dev > backup-govcert-$(date +%Y%m%d).sql
```

---

## Checklist de Produção

Antes de publicar:

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` com HTTPS real
- [ ] `FRONTEND_URL` com HTTPS real
- [ ] `APP_KEY` gerada no ambiente de produção
- [ ] SMTP real configurado (não Mailpit)
- [ ] phpMyAdmin e Mailpit desativados (`--profile dev` não subido)
- [ ] TLS configurado no Nginx ou proxy externo
- [ ] Credenciais do banco alteradas
- [ ] Seed de desenvolvimento **não usado** como credencial real
- [ ] API key configurada pelo painel (criptografada no banco), não hardcoded
- [ ] Assets buildados: `npm run build`
- [ ] Caches construídos: `config:cache`, `route:cache`, `view:cache`
- [ ] Worker com política de restart ativa
- [ ] Migrations executadas
- [ ] `.env`, logs e dependências locais fora do repositório Git
- [ ] Backups configurados

---

## Relacionados

- [README.md](README.md)
- [[docs/00 - Visão Geral]]
- [[docs/05 - Configuração de Ambiente]]
- [[docs/15 - Configurações do GovCert]]
- [[docs/18 - Build e Deploy]]
- [[docs/21 - Troubleshooting]]
