# 12 - Troubleshooting

## Erro: `No application encryption key has been specified`

Causa: `APP_KEY` vazio.

Solucao:

```bash
cp src/.env.example src/.env
docker compose exec app php artisan key:generate --force
docker compose exec app php artisan config:clear
```

## Erro: `502 Bad Gateway`

Causa provavel: `app` travado ou indisponivel.

Diagnostico:

```bash
docker compose ps
docker compose logs app
docker compose logs webserver
```

Solucao:

```bash
docker compose restart app webserver
```

## MySQL Nao Conecta

Diagnostico:

```bash
docker compose ps db
docker compose logs db
```

Tente novamente:

```bash
docker compose exec app php artisan migrate --force
```

## Tabela `sessions` Nao Existe

Solucao:

```bash
docker compose exec app php artisan migrate --force
```

## Permissao Em `storage`

Solucao:

```bash
docker compose exec app chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
docker compose exec app chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

## Login Admin Falha

Causa comum: seed nao rodou.

Solucao:

```bash
docker compose exec app php artisan db:seed --force
```

## Logs Em `pending`

Causa: worker parado ou Redis indisponivel.

Diagnostico:

```bash
docker compose ps queue_worker redis
docker compose logs queue_worker
docker compose logs redis
```

Solucao:

```bash
docker compose restart queue_worker redis
docker compose exec app php artisan queue:restart
```

## Logs Em `in_analysis`

Causa: worker travou durante execucao.

Ver falhas:

```bash
docker compose exec app php artisan queue:failed
```

Reprocessar:

```bash
docker compose exec app php artisan queue:retry all
```

## Gemini Falhando

Validar chave:

```bash
docker compose exec app php artisan tinker --execute="echo config('services.gemini.api_key') ? 'OK' : 'VAZIA';"
```

Apos corrigir:

```bash
docker compose exec app php artisan config:clear
docker compose restart queue_worker
docker compose exec app php artisan queue:retry all
```

## Extensao Retorna 401

Possiveis causas:

- token revogado;
- URL da API errada;
- login feito novamente com mesmo dispositivo;
- token corrompido.

Solucao:

1. Faca logout na extensao.
2. Gere novo token ou refaca login.
3. Confirme `APP_URL` e URL configurada na extensao.

## Extensao Nao Captura

Causa comum: DOM da plataforma mudou.

Solucao:

1. Abra DevTools.
2. Compare seletores com `chrome-extension/content.js`.
3. Atualize seletores.
4. Recarregue a extensao.

## Mixed Content

Sintoma: extensao em pagina HTTPS nao envia para API HTTP.

Solucao: use HTTPS em producao e configure `APP_URL=https://...`.
