#!/bin/sh
set -e

# Cache config/routes/events with the runtime environment (not at build time).
if [ "$1" = "apache2-foreground" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
fi

exec "$@"
