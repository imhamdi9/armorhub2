# Free deployment (demo): Render + TiDB Cloud

Everything here is free-tier. Expect a **demo**, not production:

- Render's free web services **sleep after ~15 minutes idle**; the first request
  afterwards takes about a minute to wake the API.
- Payments are **simulated** (no card is charged) — fine for a demo.
- Free tiers change; if a limit below no longer matches, the steps still apply to
  any Docker host plus any MySQL-compatible database.

You need free accounts at [GitHub](https://github.com) (already have),
[TiDB Cloud](https://tidbcloud.com) and [Render](https://render.com).

## 1. Database (TiDB Cloud, MySQL-compatible, free)

1. Sign up at tidbcloud.com → **Create cluster** → choose the free **Starter** plan.
2. Open the cluster → **Connect** → note the **host**, **port** (4000), **username**
   (looks like `abc123.root`) and generate a **password**. Allow connections from anywhere.
3. In the SQL editor run: `CREATE DATABASE armorhub;`

## 2. Deploy API + web (Render)

1. Render dashboard → **New** → **Blueprint** → connect this GitHub repo and pick
   the branch you want to deploy. Render reads `render.yaml` and proposes two
   services: `armorhub-api` and `armorhub-web`.
2. Fill the prompted values:

   | Variable | Value |
   | --- | --- |
   | `DB_HOST` | TiDB host from step 1 |
   | `DB_DATABASE` | `armorhub` |
   | `DB_USERNAME` / `DB_PASSWORD` | from step 1 |
   | `ADMIN_EMAIL` / `ADMIN_PASSWORD` | the login for your first platform admin (min 8 chars) |
   | `APP_URL` | `https://armorhub-api.onrender.com` |
   | `WEB_URL` | `https://armorhub-web.onrender.com` (emailed links and the one trusted browser origin) |
   | `API_PROXY_TARGET` (web) | `https://armorhub-api.onrender.com` |

   If Render says a service name is taken, it appends a suffix — use the URLs it
   actually shows on each service page, and update the three URL values to match.

   The web app serves the API under its own `/api` (so the browser session cookie stays first-party and
   no CORS is needed); `API_PROXY_TARGET` is where it forwards those calls.
3. Click **Apply**. The API builds first; on boot it creates the tables, the
   industries, roles and your admin automatically. Watch the API **Logs** for
   `Admin ... created` and Apache starting.
4. Open `https://armorhub-web.onrender.com/login` and sign in with `ADMIN_EMAIL`.

If you had to change a URL after the first deploy: update the env var, then
**Manual Deploy → Deploy latest commit** (the web URL is baked in at build time).

## 3. First-time setup in the app (all in the UI, signed in as the admin)

1. **Tenants** → add your brand and pick its industry (Automotive, Home Services, Beauty, Devices).
2. **Companies** → add the storefront company under that tenant, then open its **Storefront** page: turn it on,
   choose the address, headline and colour. Customers visit `/s/<address>` and sign up from there.
3. **Products** → add products and their price rules; **Partners** → add service
   providers *with latitude/longitude* so nearby customers are matched to them.
4. **Team** → create accounts for each partner's manager (role *Partner manager*, with their
   company and partner). Managers then add their own technicians; share the temporary password
   securely (they can change it with "Forgot your password?").
5. Share your store link, `https://<your-web-url>/s/<address>`; customers browse and sign up from it.

(`npm run e2e:setup` in `apps/web` replays exactly this first-run path in a browser.)

## Limits to know

- Emails (password reset, order notifications) are only written to the log until you set SMTP
  (`MAIL_MAILER=smtp` and `MAIL_HOST`/`MAIL_USERNAME`/`MAIL_PASSWORD`); the app works fully without them.
- Real card payments: sign in as admin → **Administration → Payments**, pick a provider, enter its keys and
  press **Make active** (see `DEPLOY.md`). The blueprint ships with the simulated provider allowed for the demo.
- Free instances restart often; the app is built to self-setup safely on every boot.
- Logs are on stderr (Render's log viewer). There is no shell on the free tier;
  use TiDB's SQL editor to inspect data.
- When you move to production, see `DEPLOY.md` (paid host or your own server) and
  integrate a real payment gateway first.
