#!/bin/sh
set -e

cd /var/www/html

# Volumes can reset ownership on mount — keep the writable dirs owned by php-fpm.
chown -R www-data:www-data storage bootstrap/cache || true

# Link public/storage for locally-stored media (R2 is primary, this is a fallback).
php artisan storage:link || true

# Cache config + views with the REAL runtime env (set in EasyPanel).
# NOTE: no `route:cache` — a couple of routes use closures, which can't be cached.
php artisan config:cache
php artisan view:cache

# Run migrations once on boot. Set RUN_MIGRATIONS=false in EasyPanel to disable
# (e.g. when scaling to multiple replicas — run them as a one-off instead).
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force || echo "[entrypoint] migrate failed (DB not ready?) — continuing"
fi

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/furnib.conf
