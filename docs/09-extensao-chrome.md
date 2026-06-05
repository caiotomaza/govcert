# 09 - Extensao Chrome

## Localizacao

```text
chrome-extension/
```

## Arquivos

| Arquivo | Responsabilidade |
|---------|------------------|
| `manifest.json` | Permissoes, scripts e hosts |
| `content.js` | Captura DOM das plataformas |
| `background.js` | Service worker e chamadas HTTP |
| `popup.html` | Interface da extensao |
| `popup.js` | Login, status e configuracao |
| `options.html` | Tela de opcoes |
| `options.js` | Logica das opcoes |

## Instalacao

1. Abra `chrome://extensions`.
2. Ative "Modo desenvolvedor".
3. Clique em "Carregar sem compactacao".
4. Selecione `chrome-extension/`.
5. Clique no icone da extensao.
6. Informe URL da API e credenciais.

## Como Funciona

1. O content script roda nas paginas permitidas.
2. Ele detecta campo de input, botao de envio e area de resposta.
3. Apos o usuario enviar uma mensagem, captura o input.
4. Quando a resposta aparece, captura o output.
5. Envia mensagem para o service worker.
6. O service worker chama a API Laravel.

## Autenticacao

A extensao chama:

```text
POST /api/extension/login
```

Depois usa:

```http
Authorization: Bearer {token}
```

## Manutencao De Seletores

Plataformas de IA alteram DOM com frequencia. Se a extensao parar de capturar:

1. Abra DevTools.
2. Inspecione campo de prompt.
3. Inspecione botao de envio.
4. Inspecione bloco de resposta.
5. Atualize seletores em `content.js`.
6. Recarregue a extensao.

## Cuidados

- API em HTTP pode ser bloqueada em paginas HTTPS por Mixed Content.
- Token fica no storage local do Chrome.
- A extensao nao deve chamar Gemini diretamente.
- Toda comunicacao externa deve passar pela API Laravel.
