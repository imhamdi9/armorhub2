#!/usr/bin/env bash
# Runs every browser end-to-end check against a real API + production web build.
# Needs: PHP 8.3+/composer deps installed in apps/api, node deps in apps/web, Chromium.
#   CHROMIUM_PATH=/path/to/chromium scripts/e2e-all.sh      (omit if Playwright's own browser is installed)
#   ONLY=security scripts/e2e-all.sh     run one phase: setup | demo | storefront | shipping | partners | security | admin | stripe  (default: all)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export DB_CONNECTION=sqlite
export DB_DATABASE="${E2E_DB:-/tmp/armorhub-e2e.sqlite}"
API_PORT=8000
WEB_PORT=3000

want() { [ -z "${ONLY:-}" ] || [ "$ONLY" = "$1" ]; }

cleanup() { pkill -f "artisan serve" 2>/dev/null || true; pkill -f "next start" 2>/dev/null || true; pkill -f "next-server" 2>/dev/null || true; }
trap cleanup EXIT
cleanup

fresh_db() { rm -f "$DB_DATABASE"; touch "$DB_DATABASE"; (cd "$ROOT/apps/api" && php artisan migrate:fresh --force -q); }

start_api() {  # extra env assignments as arguments
  pkill -f "artisan serve" 2>/dev/null || true; sleep 1
  (cd "$ROOT/apps/api" && env "$@" PHP_CLI_SERVER_WORKERS=4 php artisan serve --host=127.0.0.1 --port=$API_PORT >/tmp/armorhub-api.log 2>&1 &)
  for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:$API_PORT/up" >/dev/null 2>&1 && return; sleep 1; done
  echo "API did not start"; exit 1
}

echo "== Building the web app (production build, same-origin API proxy)"
cd "$ROOT/apps/web"
export API_PROXY_TARGET="http://127.0.0.1:$API_PORT" NEXT_PUBLIC_API_URL=/api NEXT_TELEMETRY_DISABLED=1
npx next build >/tmp/armorhub-web-build.log 2>&1 || { tail -20 /tmp/armorhub-web-build.log; exit 1; }
(PORT=$WEB_PORT nohup npx next start -p $WEB_PORT >/tmp/armorhub-web.log 2>&1 &)
for _ in $(seq 1 30); do curl -fsS "http://127.0.0.1:$WEB_PORT/login" >/dev/null 2>&1 && break; sleep 1; done

if want setup; then
echo; echo "== 1/8 First-run setup on an empty deployment (only the admin exists)"
fresh_db
(cd "$ROOT/apps/api" && RUN_MIGRATIONS=true ADMIN_EMAIL=admin@test.com ADMIN_PASSWORD=password123 sh docker-entrypoint.sh true >/dev/null)
start_api
(cd "$ROOT/apps/web" && npm run -s e2e:setup)
fi

if want demo; then

echo; echo "== 2-5/8 Demo marketplace: journey, staff, responsive + Arabic, management"
fresh_db
(cd "$ROOT/apps/api" && php artisan platform:demo >/dev/null)
start_api
cd "$ROOT/apps/web"
npm run -s e2e
npm run -s e2e:staff
SHOTS="${SHOTS:-/tmp}" npm run -s e2e:responsive
npm run -s e2e:manage   # last: it renames a company other checks look up
fi

if want storefront; then
echo; echo "== Public storefront: server-rendered pages, branding, Arabic/mobile, signup from a product, editor"
fresh_db
(cd "$ROOT/apps/api" && php artisan platform:demo >/dev/null)
start_api
(cd "$ROOT/apps/web" && npm run -s e2e:storefront)
fi

if want shipping; then
echo; echo "== Shipping: physical order, delivery address, any-carrier shipment, tracking"
fresh_db
(cd "$ROOT/apps/api" && php artisan platform:demo >/dev/null)
start_api
(cd "$ROOT/apps/web" && npm run -s e2e:shipping)
fi

if want partners; then
echo; echo "== Partner signup: apply, wait for approval, role granted on approval, rejection"
fresh_db
(cd "$ROOT/apps/api" && php artisan platform:demo >/dev/null)
start_api
(cd "$ROOT/apps/web" && npm run -s e2e:partners)
fi

if want security; then
echo; echo "== 6/8 Security: attacking the running app (fresh database: it exhausts rate limits on purpose)"
fresh_db
(cd "$ROOT/apps/api" && php artisan platform:demo >/dev/null)
start_api
(cd "$ROOT/apps/web" && npm run -s e2e:security)
fi

if want admin; then
echo; echo "== 7/8 Admin control panel: settings, switching payment providers live (against a fake gateway)"
fresh_db
(cd "$ROOT/apps/api" && php artisan platform:demo >/dev/null)
start_api PLATFORM_ALLOW_INSECURE_PROVIDER_URLS=true
(cd "$ROOT/apps/web" && npm run -s e2e:admin)
fi

if want stripe; then
echo; echo "== 8/8 Card payments through Stripe Checkout (against a local fake of Stripe)"
fresh_db
(cd "$ROOT/apps/api" && php artisan platform:demo >/dev/null)
start_api PLATFORM_PAYMENT_GATEWAY=stripe STRIPE_SECRET=sk_test_fake STRIPE_WEBHOOK_SECRET=whsec_e2e \
  STRIPE_API_BASE=http://127.0.0.1:12111 WEB_URL="http://localhost:$WEB_PORT"
cd "$ROOT/apps/web" && npm run -s e2e:stripe
fi

echo; echo "ALL END-TO-END CHECKS PASSED"
