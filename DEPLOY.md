# Deploying ArmorHub

Stack: Laravel API (`apps/api`), Next.js web app (`apps/web`), MySQL 8, and
Caddy for automatic HTTPS. Everything runs from one compose file.

## 1. One-command deploy (any Linux server with Docker)

1. Point two DNS records at the server: `app.<your-domain>` and `api.<your-domain>`.
2. Configure:
   ```bash
   cp .env.production.example .env.production
   # fill in every value: DOMAIN, DB passwords, CORS origin, APP_URL ...
   docker compose --env-file .env.production -f docker-compose.prod.yml build api
   docker run --rm armorhub-api php artisan key:generate --show   # paste into APP_KEY
   ```
3. Start:
   ```bash
   docker compose --env-file .env.production -f docker-compose.prod.yml up -d --build
   ```
   The `migrate` service creates the schema, the industries, and roles/permissions
   on every deploy. Caddy obtains TLS certificates automatically.
4. Create the first platform admin:
   ```bash
   docker compose --env-file .env.production -f docker-compose.prod.yml \
     exec api php artisan platform:create-admin you@example.com --name="Your Name"
   ```
5. Sign in at `https://app.<your-domain>`, open **Tenants**, create/assign each
   tenant's industry, and create the storefront company customers sign up under.

Update later with `git pull` then the same `up -d --build` command.

## 2. Before real customers use it

- **Payments are configured in the app, not in files.** Sign in as the platform admin and open
  **Administration → Payments**: choose a provider (Stripe Checkout, a custom HTTP gateway, or the demo
  simulator), enter its keys, copy the shown **webhook URL** into the provider's dashboard, press **Test**,
  then **Make active**. You can switch providers at any time; checkouts already started with another
  provider still complete. Keys are stored encrypted (so keep `APP_KEY` safe) and are never shown again.
  `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` / `PLATFORM_PAYMENT_GATEWAY` env vars are only the *initial*
  defaults; the admin panel overrides them. Orders are marked paid **only by the provider's signed webhook**
  and the amount is verified against the order. Until a real provider is active the app refuses to take
  payments in production. Refunds are done in the provider's dashboard.
- **Other runtime settings** (commission and its split, currency, signup/order switches, signup limits, bot
  challenge, email verification) live under **Administration → Settings** and need no redeploy.
- **Security:** read [`SECURITY.md`](SECURITY.md); run `php artisan platform:security-check` after deploying.
  Set `TRUSTED_PROXIES=*` behind Caddy/Render so per-IP limits see real visitors.
- **Email.** Password resets and order notifications use Laravel mail. Configure SMTP
  (`MAIL_*`) and `WEB_URL`; the default `log` mailer only writes emails to the log.
- **Customer signup** needs the storefront company's ID; share it with
  customers (e.g. in a signup link or on your site).
- **Ordering needs location.** Customers share it on the New order screen;
  partners need `latitude`, `longitude` and `location_enabled` to be found.
- Commission % and split, currency: `apps/api/config/platform.php` (env:
  `PLATFORM_COMMISSION_PERCENTAGE`, `PLATFORM_CURRENCY`).
- Hardening already in place: login/signup rate limiting, API rate limiting,
  tokens expire after 7 days, CORS limited to your web origin, debug off.
  Still recommended: email verification, and httpOnly-cookie auth instead of
  a token in `localStorage`.
- Back up the `db_data` volume.

## 3. Without Docker

API needs PHP 8.4 (the locked dependencies require it), MySQL 8 and a web
server pointing at `apps/api/public`:

```bash
cd apps/api
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # then edit .env (see .env.production.example)
php artisan migrate --force
php artisan db:seed --class=IndustrySeeder --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan config:cache && php artisan route:cache
php artisan platform:create-admin you@example.com
```

Web (the API URL is baked in at build time):

```bash
cd apps/web
NEXT_PUBLIC_API_URL=https://api.your-domain.com/api npm ci && npm run build && npm start
```

Do **not** run `php artisan db:seed` with no class on a real database: it
adds demo catalog data.

## 4. Local demo & tests

```bash
cd apps/api && php artisan migrate:fresh && php artisan platform:demo   # demo admin/partner users
php artisan serve                                                      # :8000
cd apps/web && NEXT_PUBLIC_API_URL=http://localhost:8000/api npm run dev   # :3000
php artisan test   # API tests         |   npm test   # web unit tests (in apps/web)
scripts/e2e-all.sh # every browser journey (needs `npx playwright install chromium` once, or CHROMIUM_PATH)
```

## Adding an industry

See "Industry modules" in the README: create `app/Modules/<Name>`, register
it in `AppServiceProvider::MODULES`, run `IndustrySeeder`.
