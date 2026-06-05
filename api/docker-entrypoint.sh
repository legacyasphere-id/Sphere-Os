#!/bin/sh
set -e

DB_DRIVER="${DB_CONNECTION:-mysql}"
DB_PORT_DEFAULT=$([ "$DB_DRIVER" = "pgsql" ] && echo "5432" || echo "3306")
DB_PORT="${DB_PORT:-$DB_PORT_DEFAULT}"

echo "[entrypoint] Waiting for ${DB_DRIVER} at ${DB_HOST}:${DB_PORT}..."
until php -r "
  new PDO(
    '${DB_DRIVER}:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}',
    '${DB_USERNAME}',
    '${DB_PASSWORD}'
  );
" 2>/dev/null; do
  sleep 2
done
echo "[entrypoint] Database ready."

echo "[entrypoint] Running migrations..."
php artisan migrate --force

echo "[entrypoint] Caching config / routes / views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "[entrypoint] Starting PHP-FPM..."
exec "$@"
