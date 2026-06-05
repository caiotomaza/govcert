# 11 - Deploy E Operacao

## Build De Assets

```bash
docker compose exec app npm run build
```

Saida:

```text
src/public/build/
```

## Deploy Inicial

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

## Atualizacao

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

## Operacao De Fila

Worker:

```bash
php artisan queue:work redis --queue=gemini,default --sleep=3 --tries=3 --max-time=3600 --max-jobs=500
```

Comandos:

```bash
docker compose logs -f queue_worker
docker compose exec app php artisan queue:failed
docker compose exec app php artisan queue:retry all
docker compose exec app php artisan queue:restart
```

## Operacao De Banco

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan migrate:status
docker compose exec db mysql -ugovcert_user -proot govcert_dev
```

Backup local simples:

```bash
docker compose exec db mysqldump -ugovcert_user -proot govcert_dev > backup-govcert.sql
```

## Logs

```bash
docker compose exec app tail -f storage/logs/laravel.log
docker compose logs -f app
docker compose logs -f webserver
docker compose logs -f queue_worker
```

## Checklist Producao

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` com HTTPS
- `APP_KEY` gerada
- `GEMINI_API_KEY` valida
- SMTP real configurado
- Credenciais padrao removidas
- phpMyAdmin e Mailpit desativados
- TLS ativo
- Backups configurados
- Worker com restart ativo
- Migrations rodadas
- Assets buildados
- Caches recriados
- `.env` fora do Git
