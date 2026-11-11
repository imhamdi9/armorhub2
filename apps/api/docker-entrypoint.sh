#!/bin/sh
set -e

# Hosts that can only generate a random value (e.g. Render) provide a bare base64
# string; Laravel wants "base64:<key>" (or exactly 32 raw characters).
case "$APP_KEY" in
    "" | base64:*) ;;
    *) [ ${#APP_KEY} -eq 32 ] || export APP_KEY="base64:$APP_KEY" ;;
esac
# Without any key, generate one for this run so the app can still boot.
if [ -z "$APP_KEY" ]; then
    export APP_KEY="$(php artisan key:generate --show)"
    echo "WARNING: APP_KEY was not set; using a temporary key. Set APP_KEY for a stable one."
fi

# Optional self-setup for hosts without a shell (e.g. Render's free tier).
# Safe to run on every boot: migrations and seeders are idempotent.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --class=IndustrySeeder --force
    php artisan db:seed --class=RolesAndPermissionsSeeder --force

    if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        php artisan platform:create-admin "$ADMIN_EMAIL" --name="${ADMIN_NAME:-Administrator}" \
            --password="$ADMIN_PASSWORD" --if-missing
    fi
fi

# Cache config/routes/events with the runtime environment (not at build time).
if [ "$1" = "apache2-foreground" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
fi

# Setup above runs as root and creates cache files; the web server runs as www-data and must be able to write
# to them. Without this, the first request that touches the cache fails with a 500.
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

exec "$@"
