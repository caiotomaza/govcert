# GovCert - Documentacao Tecnica

Esta pasta guarda a documentacao detalhada do GovCert. Ela trabalha junto com os dois arquivos da raiz:

- [README.md](../README.md): apresentacao geral, mapa do sistema e links principais.
- [RUNBOOK.md](../RUNBOOK.md): comandos operacionais, deploy, rotinas e troubleshooting.

Use esta pasta quando precisar entender o projeto em profundidade, estudar uma parte especifica da ferramenta ou manter a documentacao tecnica organizada por tema.

## Ordem Recomendada de Leitura

1. [Visao geral](01-visao-geral.md)
2. [Arquitetura](02-arquitetura.md)
3. [Instalacao e configuracao](03-instalacao-configuracao.md)
4. [Fluxos principais](04-fluxos-principais.md)
5. [Modulos e componentes](05-modulos-componentes.md)
6. [Banco de dados](06-banco-de-dados.md)
7. [API e rotas web](07-api-e-rotas.md)
8. [Autenticacao e autorizacao](08-autenticacao-autorizacao.md)
9. [Extensao Chrome](09-extensao-chrome.md)
10. [Testes](10-testes.md)
11. [Deploy e operacao](11-deploy-operacao.md)
12. [Troubleshooting](12-troubleshooting.md)

## Indice

| Arquivo | Conteudo |
|---------|----------|
| [01-visao-geral.md](01-visao-geral.md) | Proposito, publico-alvo, funcionalidades e limites do projeto |
| [02-arquitetura.md](02-arquitetura.md) | Componentes, comunicacao, containers e responsabilidades |
| [03-instalacao-configuracao.md](03-instalacao-configuracao.md) | Setup local, variaveis de ambiente e validacao inicial |
| [04-fluxos-principais.md](04-fluxos-principais.md) | Captura, ingestao, processamento, login e exportacao |
| [05-modulos-componentes.md](05-modulos-componentes.md) | Controllers, jobs, middlewares, models e views |
| [06-banco-de-dados.md](06-banco-de-dados.md) | Tabelas, campos, indices, relacionamentos e cuidados |
| [07-api-e-rotas.md](07-api-e-rotas.md) | Endpoints REST, rotas web e exemplos de uso |
| [08-autenticacao-autorizacao.md](08-autenticacao-autorizacao.md) | Sanctum, sessao, roles, middlewares e riscos |
| [09-extensao-chrome.md](09-extensao-chrome.md) | Instalacao, arquitetura da extensao e manutencao de seletores |
| [10-testes.md](10-testes.md) | Como rodar testes, cobertura atual e lacunas |
| [11-deploy-operacao.md](11-deploy-operacao.md) | Build, deploy, filas, banco, logs e checklist |
| [12-troubleshooting.md](12-troubleshooting.md) | Problemas comuns e solucoes praticas |

## Regra de Organizacao

- Documentacao resumida e apresentacao ficam no README da raiz.
- Procedimentos operacionais ficam no RUNBOOK da raiz.
- Detalhamento tecnico, decisoes e guias por tema ficam nesta pasta.
- Novos documentos devem usar nomes curtos, numerados e em kebab-case.
