# GovCert

Sistema de auditoria e governança do uso de Inteligência Artificial generativa em órgãos públicos.

---

## O Problema

Servidores públicos usam IAs generativas (ChatGPT, Claude, Gemini, Copilot) no dia a dia sem controle institucional. Com isso, dados protegidos por lei podem sair do órgão sem registro: CPFs de cidadãos, credenciais de sistemas, prontuários médicos, contratos sigilosos, senhas e tokens de API.

O GovCert resolve isso capturando, registrando e classificando automaticamente cada interação com IA generativa, identificando riscos de vazamento e exibindo evidências de auditoria no painel — com dados sensíveis **sempre mascarados** na interface.

---

## Como funciona

```
Navegador do Usuário
  └─ Extensão Chrome (MV3)
       ├─ content.js captura input/output no DOM
       └─ background.js envia POST /api/audit/logs
              │  Authorization: Bearer {token}
              ▼
Docker / Backend Laravel 12
  ├─ Nginx :8000
  ├─ PHP-FPM / Laravel 12
  ├─ MySQL 8.0
  └─ Redis 7
              │  dispatch ProcessAuditLog → fila gemini
              ▼
Worker
  └─ AiProviderManager → GeminiClient / OpenAiCompatibleClient
       └─ Classifica risco, identifica tipo de vazamento, conta tokens
```

O painel exibe tudo mascarado. O texto original fica no banco como evidência forense — **nenhum auditor ou administrador consegue revelar o conteúdo sensível pela interface**.

---

## Funcionalidades

| Funcionalidade | Descrição |
|---|---|
| Captura automática | Extensão detecta e registra prompts/respostas nas plataformas monitoradas |
| Ingestão assíncrona | API retorna HTTP 202 imediatamente; análise ocorre em fila |
| Análise de risco com IA | Worker classifica risco, tipo de vazamento e justificativa |
| Mascaramento obrigatório | CPF, CNPJ, e-mail, telefone, senha, token, JWT, API key — sempre ocultos na interface |
| Visão Geral | KPIs, gráficos de volume, distribuição de risco, tokens por dia |
| Auditoria | Listagem de logs com filtros, exportação CSV/PDF, detalhe mascarado |
| Tokens | Ranking de usuários com heatmap de consumo e quantidade de logs |
| Alertas de tokens | Limites configuráveis por usuário e período |
| Gestão de usuários | Admin cadastra usuários (3 perfis), envia link de criação de senha |
| Configurações do GovCert | Troca de provedor de IA, teste de API, desconexão global de usuários |
| ActivityLog | Trilha completa de ações administrativas |

---

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2, Laravel 12 |
| Autenticação API | Laravel Sanctum |
| Banco | MySQL 8.0 |
| Fila/Cache | Redis 7 |
| Frontend web | Blade, Vite, Tailwind CSS |
| Extensão | Chrome Manifest V3, JavaScript vanilla |
| PDF | barryvdh/laravel-dompdf |
| Testes | PHPUnit 11.5 |
| Containers | Docker Compose, Nginx, PHP-FPM |
| IA | Gemini (padrão), Grok, DeepSeek, OpenAI-compatível |

---

## Rodando localmente

> Pré-requisito: Docker e Docker Compose. PHP, Composer e Node **não** precisam estar instalados localmente.

```bash
# 1. Copiar ambiente
cp src/.env.example src/.env
# Edite src/.env e preencha GEMINI_API_KEY (opcional — pode configurar pelo painel depois)

# 2. Build da imagem PHP
docker compose build app

# 3. Subir containers
docker compose up -d
# Com phpMyAdmin :8080 e Mailpit :8025:
# docker compose --profile dev up -d

# 4. Instalar dependências PHP
docker compose exec app composer install

# 5. Gerar chave da aplicação
docker compose exec app php artisan key:generate --force

# 6. Rodar migrations
docker compose exec app php artisan migrate --force

# 7. Popular dados de desenvolvimento
docker compose exec app php artisan db:seed --force

# 8. Corrigir permissões de storage
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 9. Acessar
open http://localhost:8000
```

---

## Usuários de desenvolvimento

Após `db:seed --force`:

| Perfil | E-mail | Senha | Acesso |
|---|---|---|---|
| Administrador | `admin@govcert.gov.br` | `admin123` | Painel completo |
| Chefe de Segurança | `chefe@govcert.gov.br` | `admin123` | Painel completo |
| Auditor | `auditor@govcert.gov.br` | `auditor123` | Painel (sem admin) |
| Usuário da extensão | `maria.silva@govcert.gov.br` | `usuario123` | Somente extensão/API |
| Usuário inativo | `bloqueado@govcert.gov.br` | `usuario123` | Bloqueado na API |

> **Perfil `usuario`**: não acessa o painel web. É destinado exclusivamente ao login na extensão Chrome e ao envio de logs via API.

---

## Perfis de acesso

| Recurso | Admin | Auditor | Usuário |
|---|---|---|---|
| Visão Geral | ✓ | ✓ | ✗ |
| Auditoria | ✓ | ✓ | ✗ |
| Tokens | ✓ | ✓ | ✗ |
| Gestão de Usuários | ✓ | ✗ | ✗ |
| Configurações do GovCert | ✓ | ✗ | ✗ |
| Login na extensão / API | ✓ | ✓ | ✓ |

---

## Documentação

> Links no formato [[Obsidian]]. Para leitura direta, os arquivos estão em `docs/`.

### Para novos desenvolvedores
1. [[docs/00 - Visão Geral]]
2. [[docs/01 - Como Rodar o Projeto]]
3. [[docs/02 - Arquitetura]]
4. [[docs/06 - Fluxos Principais]]
5. [[docs/10 - Autenticação e Autorização]]
6. [[docs/22 - Guia para Novos Desenvolvedores]]

### Para operação
1. [[RUNBOOK]]
2. [[docs/05 - Configuração de Ambiente]]
3. [[docs/18 - Build e Deploy]]
4. [[docs/21 - Troubleshooting]]

### Para segurança e auditoria
1. [[docs/13 - Auditoria e Mascaramento de Dados]]
2. [[docs/14 - Tokens e Alertas de Uso]]
3. [[docs/15 - Configurações do GovCert]]
4. [[docs/20 - Decisões Técnicas]]

### Índice completo

| Arquivo | Conteúdo |
|---|---|
| [[docs/00 - Visão Geral]] | O que é, problema, público, módulos, papéis |
| [[docs/01 - Como Rodar o Projeto]] | Instalação local passo a passo |
| [[docs/02 - Arquitetura]] | Camadas, diagrama, decisões técnicas |
| [[docs/03 - Estrutura de Pastas]] | Árvore de diretórios comentada |
| [[docs/04 - Tecnologias e Dependências]] | Stack detalhada com papel de cada tecnologia |
| [[docs/05 - Configuração de Ambiente]] | Variáveis de ambiente |
| [[docs/06 - Fluxos Principais]] | Captura, ingestão, análise, mascaramento, tokens |
| [[docs/07 - Módulos e Componentes]] | Controllers, Services, Jobs, Models |
| [[docs/08 - Banco de Dados]] | Schema, tabelas, relacionamentos |
| [[docs/09 - APIs e Endpoints]] | Rotas web e API REST documentadas |
| [[docs/10 - Autenticação e Autorização]] | Roles, Sanctum, sessões, permissões |
| [[docs/11 - Interface Web]] | Telas, componentes, padrões visuais |
| [[docs/12 - Extensão Chrome]] | MV3, content.js, background.js, login, limitações |
| [[docs/13 - Auditoria e Mascaramento de Dados]] | Mascaramento obrigatório, dados detectados |
| [[docs/14 - Tokens e Alertas de Uso]] | Contagem, ranking, heatmap, alertas |
| [[docs/15 - Configurações do GovCert]] | Provedores de IA, teste de API, logout all |
| [[docs/16 - Seeds e Dados de Teste]] | Usuários, perfis, senhas, distribuição de dados |
| [[docs/17 - Testes]] | PHPUnit, como rodar, suítes disponíveis |
| [[docs/18 - Build e Deploy]] | Docker, assets, deploy seguro |
| [[docs/19 - Padrões de Código]] | Convenções, organização, ActivityLog |
| [[docs/20 - Decisões Técnicas]] | Justificativas das escolhas arquiteturais |
| [[docs/21 - Troubleshooting]] | Erros comuns e soluções |
| [[docs/22 - Guia para Novos Desenvolvedores]] | Onde começar, primeira alteração |
