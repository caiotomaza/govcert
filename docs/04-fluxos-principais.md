# 04 - Fluxos Principais

## Captura E Ingestao

1. Usuario acessa uma plataforma de IA.
2. `content.js` identifica elementos da pagina.
3. O usuario envia um prompt.
4. A extensao captura `input_text`.
5. A resposta aparece no DOM.
6. A extensao captura `output_text`.
7. `content.js` envia a mensagem para `background.js`.
8. `background.js` envia `POST /api/audit/logs`.
9. A API valida token Sanctum.
10. O log e gravado com `status=pending`.
11. O job `ProcessAuditLog` e despachado para a fila `gemini`.
12. A API responde `202 Accepted`.

## Processamento Assincrono

1. `queue_worker` consome a fila.
2. O job busca o registro em `audit_logs`.
3. Se o status for `completed` ou `failed`, o job encerra.
4. O status vira `in_analysis`.
5. O payload para Gemini e montado com URL, input e output.
6. Gemini retorna JSON estruturado.
7. O job grava:
   - `has_sensitive_data`
   - `risk_level`
   - `leak_type`
   - `gemini_justification`
   - `gemini_raw_response`
   - `processed_at`
8. Em sucesso, status vira `completed`.
9. Em falha definitiva, status vira `failed`.

## Login Da Extensao

1. Usuario informa API URL, e-mail e senha.
2. `popup.js` chama `/api/extension/login`.
3. O backend valida credenciais.
4. Se `is_active=false`, retorna 403.
5. Tokens antigos do mesmo dispositivo sao removidos.
6. Novo token Sanctum e criado.
7. Token e dados do usuario ficam em `chrome.storage.local`.

## Login Web

1. Usuario acessa `/login`.
2. Envia e-mail e senha.
3. Laravel cria sessao.
4. Rotas protegidas usam `auth`.
5. Rotas protegidas tambem usam `active`.
6. Rotas admin usam `admin`.

## Exportacao

### CSV

Rota:

```text
GET /audit/logs/export/csv
```

Usa streaming para reduzir consumo de memoria.

### PDF

Rota:

```text
POST /audit/logs/export/pdf
```

Usa DomPDF com view Blade propria.

## Estados Do Log

| Status | Significado |
|--------|-------------|
| `pending` | Recebido, aguardando worker |
| `in_analysis` | Worker iniciou analise |
| `completed` | Analise concluida |
| `failed` | Falhou apos tentativas |
