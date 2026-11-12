# Launch checklist

Work through this top to bottom before inviting real customers. `php artisan platform:security-check` reports most of the configuration items for you; the browser audits (`ONLY=audit,a11y scripts/e2e-all.sh`) cover every page and button for every role.

## 1. Must do (the platform is not safe or usable without these)

| | Item | How |
|---|---|---|
| ☐ | **HTTPS everywhere** | `APP_URL` and `WEB_URL` start with `https://`. Sessions and passwords must never travel in clear text. |
| ☐ | **Real payments** | Choose a provider in Admin → Payments (Stripe or your own gateway), then remove `PLATFORM_ALLOW_SIMULATED_PAYMENTS`. The simulated provider collects no money. |
| ☐ | **Real email** | Set SMTP (`MAIL_MAILER=smtp`, host, user, password, `MAIL_FROM_ADDRESS`). Without it password resets, order notifications and email verification do nothing. |
| ☐ | **Permanent file storage** | Create a private S3/R2 bucket, then set `DOCUMENTS_DISK=s3` (seller and partner verification documents) and `MEDIA_DISK=s3` (product photos, logos, hero images) with the `AWS_*` variables. A host with a temporary disk (Render free) erases uploads on restart. |
| ☐ | **Behind a proxy** | `TRUSTED_PROXIES=*` (or the proxy's addresses) so each visitor has their own IP and rate limits work. |
| ☐ | **No demo data** | Never run `platform:demo` or `platform:demo-egypt` in production. Create the first administrator with `ADMIN_EMAIL` / `ADMIN_PASSWORD` once, then change the password and remove the variables. |
| ☐ | **Backups** | Daily database backups with a restore you have tried once. Uploaded files live in the bucket, so enable bucket versioning. |
| ☐ | **Set the public address** | `SITE_URL` on the web service, so `sitemap.xml`, canonical links and social cards use the real domain. |

## 2. Should do (strongly recommended)

| | Item | How |
|---|---|---|
| ☐ | Bot protection | Admin → Security: turn on Turnstile or hCaptcha for signup and applications. |
| ☐ | Email verification | Admin → Settings: require a verified email to order. |
| ☐ | Legal pages | Terms, privacy and refund policy are linked from the footer and the cookie banner. Have them reviewed for your countries. |
| ☐ | Monitoring | Alert on `/up` failing, on 5xx responses, and on payment-webhook failures (Admin → Platform → Event log shows failed deliveries). |
| ☐ | Content approval | If more than one person edits the site, turn on Settings → Content → approval so a second admin signs off. |
| ☐ | Support route | A monitored address for the "Contact support" and "Book an onboarding call" links. |

## 3. Per market (before opening each country)

- The market pack (currency, VAT, phone and tax-number formats) is correct for the country.
- At least one verified partner or seller is live, so a visitor never lands on an empty store.
- Shipping: a carrier provider is connected, or the manual provider is understood by sellers.
- Payouts: the bank-transfer process for seller payouts has an owner (see SELLERS.md).

## 4. Checked in this repository

- Every role (admin, owner, partner manager, technician, seller, customer) and the public site were opened page by page and every button pressed: no script errors, failed requests, dead links, broken images or sideways scrolling left.
- Accessibility (axe, WCAG 2 A/AA) in English and Arabic: no serious or critical issues.
- Public pages have titles, descriptions, canonical links, social cards and, for products, structured data built only from real fields (no invented ratings).
- Sign-in, sign-up and the dashboard are not indexed by search engines.
- Server-side tests, unit tests and 100+ browser checks run on every change (`scripts/e2e-all.sh`).
