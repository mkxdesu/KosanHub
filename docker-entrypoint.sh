#!/bin/sh
set -e

mkdir -p storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache/data \
         storage/logs \
         bootstrap/cache

if [ ! -f .env ] && [ -f .env.example ] && [ -z "$APP_KEY" ] && [ -z "$DB_CONNECTION" ]; then
    cp .env.example .env
fi

if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    php artisan migrate --force
fi

exec "$@"
