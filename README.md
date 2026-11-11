# ArmorHub

A multi-tenant marketplace platform that connects **customers** with trusted **service providers (partners)**.
It started as an automotive-protection marketplace (PPF, ceramic coating, window tint, detailing) and is now
industry-agnostic: any business with the same flow can be added as an industry module.

> A customer orders a service → nearby partners send quotes → the customer accepts one and pays →
> the partner schedules and completes the job → the platform splits a commission and issues a warranty.

## What's in the box

| Area | Features |
| --- | --- |
| **Industries** | Automotive, Home Services, Beauty & Wellness, Devices — each a self-contained module |
| **Countries** | Saudi Arabia and Egypt first, as data-driven market packs (currency, VAT, phone rules, cities); a new country is a config entry. See [MARKETS.md](MARKETS.md) |
| **Public site** | A server-rendered home page and a **branded storefront per business** (`/s/<slug>`): hero, featured products, searchable catalog, product pages with public prices, partner showcase, sitemap/robots, English + Arabic. Visitors sign up straight from a product page |
| **Customers** | Sign up, add what they bring (property…), order with live pricing, compare quotes, pay, track a timeline, cancel before paying, warranties |
| **Partners** | Quote inbox, jobs (schedule → start → complete), team of technicians, earnings |
| **Companies / admins** | Tenants & industries, companies, **storefront editor** (address, headline, colour, logo, featured products), products & price rules, shared catalogs, partners, staff with roles, commissions & payouts, dashboards with role-based numbers |
| **Payments** | Pluggable providers chosen **and switched live from the admin panel**: Stripe Checkout, a configurable HTTP gateway for most hosted-checkout providers, or a simulated one for demos. Signed webhooks, amount verified, idempotent |
| **Admin control panel** | Overview with an attention list, runtime settings (commission, currency, signup/order switches, security knobs), payments, security health & emergency controls, audit log |
| **Notifications** | In-app inbox + email for quotes, acceptance, payments, progress |
| **Security** | Anti-Sybil/bot defences, HttpOnly cookie sessions, CSRF checks, CSP, tenant isolation, audit trail. See [`SECURITY.md`](SECURITY.md) |
| **Accounts** | Roles & permissions, staff management, deactivation, email verification, password reset |
| **UI** | Next.js app, responsive, **English + Arabic (RTL)** |

## Architecture

- `apps/api` — Laravel 12 API (Sanctum tokens, Spatie permissions). Industry-neutral core
  (tenants, companies, partners, products, orders, quotes, payments, commissions) plus
  `app/Modules/<Industry>` plug-ins.
- `apps/web` — Next.js 16 (App Router) dashboard.
- `infrastructure/` — Docker/Caddy configs; `render.yaml` for a free-tier demo deploy.

### Industry modules

Everything vertical-specific lives in `apps/api/app/Modules/<Industry>`:

- a class implementing `App\Modules\Contracts\IndustryModule` (extend `BaseIndustryModule`)
- its own models: the **subject** a customer brings (vehicle model, property, device) and the **targets**
  a price rule applies to (vehicle part, room type, device component), plus optional shared lists
- optional custom policies, routes, permissions and a `PricingStrategy`

Register it in `AppServiceProvider::MODULES`, run `IndustrySeeder`, and assign it to a tenant. The web app adapts
its navigation and forms to the signed-in user's industry automatically.

## Run it locally

Requirements: PHP 8.4 (8.3 works with a re-resolved lock file), Composer, Node 22.

```bash
# API
cd apps/api
composer install && cp .env.example .env && php artisan key:generate
php artisan migrate:fresh && php artisan platform:demo     # demo admin, storefront, partner
php artisan serve                                          # http://localhost:8000

# Web
cd apps/web && npm ci
NEXT_PUBLIC_API_URL=http://localhost:8000/api npm run dev  # http://localhost:3000
```

Demo logins (password `password123`): `admin@test.com`, `shop@test.com`. The public site is at http://localhost:3000/ and the demo store at
http://localhost:3000/s/armor-store; customers sign up from there (or at `/register?store=armor-store`).

## Tests

```bash
cd apps/api && php artisan test           # 143 API tests (orders, payments, providers, security, permissions, …)
cd apps/web && npm test                   # unit tests (navigation, i18n dictionary, formatting)
scripts/e2e-all.sh                        # every browser journey, incl. Arabic/mobile and Stripe
```

`scripts/e2e-all.sh` covers: first-run setup, the public storefront (checked with JavaScript off, as a search engine sees it), the full customer→partner→payment→job→commission journey, staff
management, responsive + Arabic/RTL (54 page views), admin management screens, a **security attack script**
(cookie theft, CSRF, brute force, signup floods, bots, headers/CSP), the admin panel (connecting and switching
payment providers live against a fake gateway), and Stripe Checkout against a local fake of Stripe. CI (`.github/workflows/ci.yml`) runs the API and web unit tests, lint and build.

## Deploying

- **Free demo:** [`FREE-DEPLOY.md`](FREE-DEPLOY.md) (Render + TiDB Cloud, using `render.yaml`).
- **Production:** [`DEPLOY.md`](DEPLOY.md) (`docker-compose.prod.yml`: MySQL, API, web, automatic HTTPS).
- **Security checklist:** [`SECURITY.md`](SECURITY.md).

## Known limitations

- Refunds and payouts to partners are tracked in the app but money movement is done outside it.
- There is no two-factor authentication yet.
- Notification *bodies* are generated in English by the API (titles and the whole UI are translated).
- Verified on SQLite in tests/browser runs; MySQL is expected to work but has not been exercised here.

## License

Proprietary
