# 00 - Visão Geral

## O que é o GovCert

O GovCert é um sistema de **auditoria e governança do uso de IA generativa em órgãos públicos**. Ele monitora, registra e classifica automaticamente as interações entre servidores e ferramentas como ChatGPT, Claude, Google Gemini e Microsoft Copilot.

O sistema combina três componentes principais: uma extensão Chrome que captura as interações no navegador, uma API Laravel que recebe e persiste os logs, e um worker assíncrono que analisa o conteúdo com um provedor de IA configurável.

## O Problema que Resolve

Servidores públicos usam IA generativa diariamente para redigir documentos, analisar processos e consultar informações. Nesse processo, dados protegidos por lei podem ser enviados para serviços externos sem registro ou controle institucional:

- CPFs e dados pessoais de cidadãos (LGPD, art. 5º)
- Dados de saúde e prontuários (LGPD, art. 11 — categoria especial)
- Credenciais de sistemas governamentais
- Senhas, tokens de API e chaves de acesso
- Contratos sigilosos e processos administrativos
- Código-fonte de sistemas internos

O GovCert reduz esse risco capturando cada interação, classificando automaticamente o risco de vazamento e colocando evidências de auditoria à disposição das equipes de segurança — tudo sem bloquear o trabalho dos servidores.

## Público-Alvo

| Papel | Uso |
|---|---|
| Administrador do sistema | Configura o sistema, gerencia usuários, monitora configurações |
| Auditor de segurança | Revisa logs, exporta relatórios, monitora riscos |
| Servidor monitorado | Usa a extensão Chrome; aparece nos relatórios |
| Time de TI/DevOps | Instala, opera e mantém a infraestrutura |

## Módulos Principais

```
┌─────────────────────────────────────────────┐
│                   GovCert                   │
├──────────────┬──────────────┬───────────────┤
│ Extensão     │  Backend     │  Painel Web   │
│ Chrome MV3   │  Laravel 12  │  Blade +      │
│              │  + Redis     │  Tailwind     │
│ Captura      │  API REST    │  Visão Geral  │
│ prompts e    │  Autenticação│  Auditoria    │
│ respostas    │  Fila        │  Tokens       │
│              │  Worker IA   │  Usuários     │
│              │              │  Configurações│
└──────────────┴──────────────┴───────────────┘
```

## Fluxo Resumido

1. **Servidor** usa ChatGPT, Claude ou Gemini no navegador.
2. **content.js** detecta o envio e captura o texto do prompt e da resposta.
3. **background.js** envia `POST /api/audit/logs` com o Bearer token do usuário.
4. **API Laravel** valida, persiste o log com `status=pending` e coloca na fila.
5. **Worker** consome a fila, chama o provedor de IA configurado e atualiza o log com classificação de risco.
6. **Painel web** exibe os logs com dados sensíveis **sempre mascarados**.

## Telas Principais

### Visão Geral

Dashboard com KPIs do período selecionado:
- Total de logs capturados
- Logs aguardando/em análise
- Logs com dados sensíveis detectados
- Risco crítico
- Gráficos de volume por dia, distribuição por risco, consumo de tokens

### Auditoria

Listagem de todos os logs com filtros por data, usuário, risco e status. Ao clicar em um log, abre um modal com a simulação do chat — **input e output exibidos com dados sensíveis mascarados**. Exportação em CSV e PDF.

### Tokens

Ranking de usuários por consumo de tokens com **heatmap direto nas colunas** de total de tokens e quantidade de logs. Gerenciamento de alertas de limite por usuário.

### Gestão de Usuários

Cadastro, edição, ativação/desativação e envio de link de criação de senha. Exclusivo para administradores.

### Configurações do GovCert

Configuração do provedor de IA ativo (Gemini, Grok, DeepSeek, customizado), teste de API, desconexão global de usuários. Exclusivo para administradores.

## Papéis (Roles)

O sistema tem três perfis de acesso:

### admin
Acesso completo ao painel web, incluindo gestão de usuários e Configurações do GovCert.

### auditor
Acesso ao painel web com exceção de Gestão de Usuários e Configurações do GovCert.

### usuario
**Não acessa o painel web.** Destinado a servidores monitorados pela extensão. Pode autenticar na extensão Chrome e enviar logs via API. Se tentar acessar o painel, é redirecionado com a mensagem: _"Seu perfil permite apenas o uso da extensão GovCert."_

## Decisão sobre Mascaramento

O GovCert adota como princípio arquitetural que **dados sensíveis nunca são exibidos na interface** — nem para auditores, nem para administradores.

O motivo é duplo:

1. **Segurança**: o painel web pode ser acessado de computadores menos seguros. Exibir o dado original ampliaria a superfície de exposição.
2. **Privacidade**: auditores precisam saber *que* houve vazamento e *qual o risco*, não necessariamente ver o dado completo.

O texto original permanece no banco de dados como **evidência forense**, acessível apenas via acesso direto ao banco em contextos de investigação formal, com os controles adequados.

## Provedores de IA

O GovCert iniciou com Google Gemini como único provedor. A arquitetura foi evoluída para suportar múltiplos provedores de forma configurável:

| Provedor | Status |
|---|---|
| Google Gemini | Implementado e testado |
| Grok (xAI) | Implementado (OpenAI-compatível) |
| DeepSeek | Implementado (OpenAI-compatível) |
| Customizado/interno | Implementado (OpenAI-compatível) |

O objetivo de longo prazo é permitir o uso de **modelos em servidores internos do órgão** (via LLM local ou API privada), eliminando completamente o envio de dados para provedores externos durante a análise.

## Relacionados

- [[01 - Como Rodar o Projeto]]
- [[02 - Arquitetura]]
- [[10 - Autenticação e Autorização]]
- [[13 - Auditoria e Mascaramento de Dados]]
- [[15 - Configurações do GovCert]]
- [[22 - Guia para Novos Desenvolvedores]]
