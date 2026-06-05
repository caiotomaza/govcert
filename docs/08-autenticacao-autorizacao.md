# 08 - Autenticacao E Autorizacao

## Estrategias

O sistema usa dois mecanismos:

| Contexto | Mecanismo |
|----------|-----------|
| API e extensao | Laravel Sanctum, Bearer Token |
| Painel web | Sessao Laravel, cookie |

## Bearer Token

Fluxo:

1. Usuario faz login na extensao.
2. Backend valida e-mail, senha e status.
3. Token Sanctum e criado.
4. Token em claro e enviado uma vez ao cliente.
5. Cliente usa `Authorization: Bearer`.

Caracteristicas:

- token em banco fica como hash;
- token nao expira automaticamente;
- logout revoga o token atual;
- usuario pode gerar/revogar token pela tela de perfil.

## Sessao Web

Fluxo:

1. Usuario acessa `/login`.
2. Laravel valida credenciais.
3. Sessao e criada.
4. Cookie `laravel_session` autentica proximas requisicoes.

## Roles

| Role | Permissoes |
|------|------------|
| `admin` | Acesso completo, incluindo usuarios e activity log |
| `auditor` | Dashboard, logs, exportacoes, perfil e tokens |

## Middlewares

| Middleware | Onde Atua |
|------------|-----------|
| `auth` | Rotas web autenticadas |
| `active` | Bloqueia usuarios inativos no painel |
| `admin` | Restringe `/admin/*` |
| `auth:sanctum` | Protege rotas API |

## Pontos De Atencao

- Rotas API com `auth:sanctum` nao passam pelo middleware web `active`.
- Se um usuario for desativado depois de emitir token, o token deve ser revogado.
- Tokens sem expiracao exigem rotina operacional de revisao.
- Adicione rate limiting antes de producao.
- `APP_DEBUG` deve ser `false` fora do ambiente local.

## Recomendacoes

- Aplicar verificacao de conta ativa tambem em rotas API.
- Definir expiracao para tokens da extensao.
- Adicionar throttle em login e ingestao.
- Auditar tokens antigos periodicamente.
- Usar HTTPS obrigatorio em producao.
