#!/bin/sh
set -e

cd /var/www/html

# Windows bind-mount: storage/bootstrap often owned by root — PHP-FPM (www-data) must write here
mkdir -p storage/framework/{cache/data,sessions,views,testing} storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R ug+rwx storage bootstrap/cache 2>/dev/null || true

if [ ! -d vendor ]; then
  echo "[safircharge] Installing Composer dependencies..."
  composer install --no-interaction --prefer-dist --optimize-autoloader
fi

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:" ]; then
  php artisan key:generate --force --no-interaction || true
fi

if [ -n "$DB_HOST" ]; then
  echo "[safircharge] Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
  until mysqladmin ping -h"$DB_HOST" -P"${DB_PORT:-3306}" -u"$DB_USERNAME" -p"$DB_PASSWORD" --silent; do
    sleep 2
  done
  echo "[safircharge] Database is ready."
  php artisan migrate --force --no-interaction
fi

exec docker-php-entrypoint php-fpm
