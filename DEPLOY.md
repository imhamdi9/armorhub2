# Deploying ArmorHub

Two services: the Laravel API (`apps/api`) and the Next.js web app (`apps/web`).

## API

Requires PHP 8.4 (the locked dependencies need it; the dev image in
`infrastructure/docker/php` already uses 8.4), MySQL 8, and a web server
pointing at `apps/api/public` (see `infrastructure/docker/nginx/default.conf`).

```bash
cd apps/api
composer install --no-dev --optimize-autoloader
cp .env.example .env            # then edit, see below
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force     # industries, roles/permissions, demo catalogs
php artisan config:cache && php artisan route:cache
```

Production `.env` essentials:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.your-domain.com
DB_CONNECTION=mysql
DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...
```

`db:seed` also runs `VehicleCatalogSeeder`/`IndustryCatalogSeeder`, which
create demo catalog data. Skip those two on a real database and run only
`IndustrySeeder` and `RolesAndPermissionsSeeder`:

```bash
php artisan db:seed --class=IndustrySeeder --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
```

Create the first platform admin (then change the password):

```bash
php artisan tinker --execute '$u = App\Models\User::create(["name"=>"Admin","email"=>"you@example.com","password"=>"CHANGE-ME-NOW"]); $u->assignRole("platform-admin");'
```

Then sign in to the web app, open **Tenants**, and assign each tenant its industry.

## Web

The API URL is baked in at build time.

```bash
docker build --build-arg NEXT_PUBLIC_API_URL=https://api.your-domain.com/api \
  -t armorhub-web apps/web
docker run -p 3000:3000 armorhub-web
```

Or without Docker: `cd apps/web && NEXT_PUBLIC_API_URL=... npm ci && npm run build && npm start`.

## Adding an industry

See "Industry modules" in the README: create `app/Modules/<Name>`, register it
in `AppServiceProvider::MODULES`, run `IndustrySeeder`.
