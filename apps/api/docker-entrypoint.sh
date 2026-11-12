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
    # One market per country: fold older per-industry markets into it (does nothing when already done).
    php artisan markets:consolidate

    if [ -n "$ADMIN_EMAIL" ] && [ -n "$ADMIN_PASSWORD" ]; then
        php artisan platform:create-admin "$ADMIN_EMAIL" --name="${ADMIN_NAME:-Administrator}" \
            --password="$ADMIN_PASSWORD" --if-missing
        # Locked out? Set ADMIN_RESET_PASSWORD=true (with ADMIN_EMAIL and a new ADMIN_PASSWORD), redeploy, sign in, then remove it.
        if [ "$ADMIN_RESET_PASSWORD" = "true" ]; then
            php artisan platform:create-admin "$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" --reset-password
        fi
    fi

    # Optional demo marketplace (Egyptian stores, sellers, partners, products and pictures). Needs its own password for the demo logins.
    # Safe on every boot (it only creates what is missing, and re-writes pictures a temporary disk has lost). Remove the variable to stop.
    if [ "$SEED_EGYPT_DEMO" = "true" ] && [ -n "$DEMO_PASSWORD" ]; then
        php artisan platform:demo-egypt --force --no-site --password="$DEMO_PASSWORD" || echo "WARNING: demo seeding failed; continuing."
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
