# 01 - Visao Geral

## O Que E o GovCert

GovCert e uma ferramenta de auditoria e governanca para monitorar o uso de inteligencia artificial generativa em ambientes institucionais. O sistema registra interacoes feitas por usuarios em plataformas de IA, analisa o conteudo e aponta riscos de vazamento de dados sensiveis.

A ferramenta foi pensada para orgaos publicos, equipes de seguranca da informacao, auditores internos e administradores que precisam acompanhar o uso de IA sem depender apenas de politicas manuais ou orientacoes informais.

## Problema Resolvido

Servidores e colaboradores podem colar em ferramentas externas informacoes como CPFs, dados de saude, credenciais, documentos internos, contratos, trechos de codigo ou informacoes protegidas por sigilo. Mesmo quando isso ocorre sem intencao maliciosa, o resultado pode gerar risco juridico, operacional e reputacional.

O GovCert cria uma trilha de rastreabilidade:

- registra quem enviou a interacao;
- registra origem, input e output;
- processa o conteudo por fila;
- classifica risco e tipo de vazamento;
- permite auditoria posterior via painel;
- exporta relatorios em CSV e PDF.

## Publico-Alvo

| Publico | Necessidade |
|---------|-------------|
| Administradores | Gerenciar usuarios, tokens e status de acesso |
| Auditores | Revisar logs, filtrar ocorrencias e exportar relatorios |
| Seguranca da informacao | Identificar vazamentos, credenciais e padroes de risco |
| Gestores publicos | Ter indicadores sobre uso de IA generativa |
| Desenvolvedores | Manter API, extensao, jobs e infraestrutura |

## Funcionalidades Principais

| Funcionalidade | Descricao |
|----------------|-----------|
| Captura por extensao | A extensao Chrome captura input e output em plataformas de IA |
| API de ingestao | O backend recebe logs autenticados por Bearer Token |
| Processamento em fila | A analise roda de forma assincrona para nao travar o navegador |
| Classificacao com Gemini | O worker chama o Gemini e grava risco, justificativa e tipo de vazamento |
| Dashboard | Exibe indicadores, volume por periodo e distribuicao de risco |
| Listagem de logs | Permite filtrar por data, usuario, risco e status |
| Exportacoes | Gera CSV e PDF |
| Gestao de usuarios | Admin edita usuarios, roles, status e reset de senha |
| Tokens da extensao | Usuarios geram e revogam credenciais de uso |
| ActivityLog | Registra acoes administrativas e eventos de seguranca |

## Componentes do Sistema

| Componente | Papel |
|------------|-------|
| `chrome-extension/` | Cliente instalado no navegador |
| `src/` | Aplicacao Laravel |
| `queue_worker` | Worker que processa logs pendentes |
| `db` | MySQL com usuarios, logs e sessoes |
| `redis` | Fila, cache e suporte ao worker |
| `webserver` | Nginx exposto na porta 8000 |

## Estado Atual

O projeto possui:

- backend Laravel com API, painel web e autenticao;
- migrations e seeders para ambiente local;
- extensao Chrome em Manifest V3;
- fila Redis para analise com Gemini;
- testes de feature e unitarios para fluxos principais;
- Docker Compose com app, webserver, banco, redis, worker e scheduler.

## Limites Conhecidos

- A captura depende de seletores CSS das plataformas externas.
- A API de ingestao nao tem limite explicito para tamanho de `input_text` e `output_text`.
- Tokens da extensao nao expiram automaticamente.
- Rotas API protegidas por Sanctum nao passam pelo middleware web `active`.
- Nao ha rate limiting configurado por padrao.
- O campo `gemini_raw_response` pode conter dados sensiveis.
