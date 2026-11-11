# Security

What the platform defends against, how, and what an operator must configure. Every defence listed here is
covered by an automated test (`apps/api/tests/Feature/SecurityTest.php`, `PaymentProvidersTest.php`,
`SettingsAndAuditTest.php`) and, where it matters, by a real-browser attack script (`npm run e2e:security`).

## Threats and defences

### Fake-account floods (Sybil attacks)
| Attack | Defence |
| --- | --- |
| Script creates thousands of accounts | Signups are limited **per IP** (default 5/hour, 30/day, admin-tunable). The limit is deliberately *not* keyed on email, which a bot simply rotates. |
| One mailbox used as many accounts (`a+1@x.com`, `a.b@gmail.com`) | Emails are reduced to their canonical mailbox; a second alias of the same mailbox is refused. |
| Throwaway emails | Built-in disposable-domain list (including subdomains) plus an admin-managed blocklist. |
| Accounts for addresses the person doesn't own | Email verification before ordering (automatic once real email delivery is configured; admin can force it). |
| Spamming partners with quote requests | Max unpaid orders per customer, 10 orders/hour, and an admin kill switch to pause ordering or signup entirely. |

### Bots
- A hidden **honeypot** field, and a **signed, single-use, IP-bound timing token**: forms submitted too fast, replayed, forged or from another IP are refused with a message that reveals nothing.
- Optional **Cloudflare Turnstile / hCaptcha / reCAPTCHA** (admin setting). It **fails closed**: a provider outage or missing key never lets traffic through.
- Password-reset requests get the same checks, so the form cannot be used to spam a victim's inbox; the answer is identical whether or not the email has an account.

### Credential attacks and account takeover
- Login limits per account+IP, per account across all IPs (credential stuffing) and per IP (one machine, many accounts), without letting an attacker lock the real owner out.
- **Timing-safe login**: an unknown email costs the same as a wrong password; the error text is identical.
- Passwords: 10+ characters with letters and numbers, and (in production) checked against known breaches.
- Failed and successful sign-ins are **audited**; the Security page shows the last day's activity and the worst offending IPs.
- Sessions are capped per account, expire after 7 days, and are revoked on password reset or deactivation. Admins can sign a user out everywhere, or everyone (emergency button).

### Session theft and cross-site attacks
- Browsers get an **HttpOnly, SameSite=Lax cookie**; the token never exists in JavaScript-readable storage, so an XSS bug cannot steal sessions. (`e2e:security` proves this in a real browser.)
- **CSRF**: cookie-authenticated writes must come from our own origin (Origin/Referer check). Bearer-token API clients are unaffected.
- A strict **Content-Security-Policy** (no third-party scripts except the optional captcha provider), `frame-ancestors 'none'`, `nosniff`, HSTS, no-store on API responses. CORS is an explicit allow-list, never `*`.

### The public storefront
- The public API (`/api/public/*`) is **read-only, throttled and cacheable**, and returns a short, explicit list of fields: store branding, product names/descriptions/prices, and partner *counts and city names*. A test scans every public response and fails if emails, phone numbers, coordinates, commission, ids of private records or settings ever appear.
- Closed, inactive or suspended stores return 404 everywhere (page, catalog, directory, sitemap). The site keeps its own short cache that remembers "not found", so a closed store cannot linger from a stale copy.
- What store owners type is **text, never markup**: React escapes it, the brand colour must be a plain `#rrggbb` (it goes in a style attribute) and the logo must be an `https://` address. The editor refuses anything else, and a browser test confirms a hostile headline is displayed literally and never runs. The CSP allows images from any https host only for these logos.
- Slugs are validated, unique and exclude the site's own addresses.

### Data isolation and authorization
- Every API route requires authentication except a short allow-list; a test walks **every route** and fails if a new one is public by accident.
- Tenants only see and order **their own industry's active products**; guessing ids returns 404. Orders, quotes, payments, commissions and users are scoped by company/partner/owner. Staff can only create roles at or below their own and cannot promote themselves.
- Products, partners and catalog items that have history cannot be deleted (database-level `RESTRICT`), so financial records survive.

### Payments
- Orders are marked paid **only by a signed provider webhook**, never by the browser. The signature is verified, the amount and currency are checked against the order (underpayment is ignored and audited), and processing is idempotent.
- Provider keys are stored **encrypted** in the settings table, are write-only in the admin UI and API, and never appear in logs or the audit trail.
- The custom gateway driver only calls **public https** addresses (no private ranges, loopback or cloud-metadata endpoints) and never follows redirects.

### Supply chain
- `composer audit` and `npm audit` report no known vulnerabilities for production dependencies. Run them regularly (CI is the right place).

## Operator checklist (production)
1. `APP_ENV=production`, `APP_DEBUG=false`, HTTPS everywhere, and run `php artisan platform:security-check` (the same checks appear under **Admin → Security**).
2. Set `TRUSTED_PROXIES` when behind a reverse proxy, otherwise per-IP limits see only the proxy.
3. Serve the API under the web app's own `/api` (the supplied Caddy/Render configs do): the session cookie then stays first-party and CORS is not needed.
4. Configure real email (SMTP) so verification and password reset work, then leave verification on **Automatic**.
5. Enable a bot challenge (Turnstile is free) under **Admin → Settings → Security**.
6. Connect a real payment provider under **Admin → Payments** before launch; the simulated provider is refused in production unless explicitly allowed.
7. Back up the database (it also holds encrypted settings: keep `APP_KEY` safe, because settings secrets cannot be decrypted without it).
8. Schedule `php artisan schedule:run` every minute (prunes old audit entries and expired tokens).

## Known limitations
- Account existence can still be probed through the signup form ("email already taken"); this is rate-limited and captcha-protectable but not eliminated.
- Email verification and the breached-password check depend on outbound email / internet access.
- There is no two-factor authentication yet.
- Verified against SQLite and a browser here; run the test suite against your production database engine before launch.

## Reporting a vulnerability
Email the maintainers privately; please don't open a public issue.

### Verification documents
- Stored on a private disk under random names; no public URL exists. Only signed-in reviewers can open them (platform admins; a store's owner only for partner applications to their own store), and every view is audited.
- Files are accepted by what they are (PDF, JPEG, PNG by content), capped at 5 MB, and downloaded with `nosniff` and `no-store`.
- Approval needs the reviewer's explicit confirmation and all required documents.
- On hosts that reset their disk, connect an S3/R2 bucket (`DOCUMENTS_DISK=s3`); the security page warns until you do. Rejected applications' files can be purged with `applications:prune-documents`.

### Partner applications
- A business applies publicly (same bot checks and per-address limit as customer signup). Nothing is created until a reviewer approves: no account, no role, no partner profile.
- The password chosen at application time is stored hashed and becomes the account password on approval; it is never returned by any endpoint.
- Only platform admins and the owners of the store applied to can see or decide an application; anyone else gets 404. A decision is final and a mailbox that registered in the meantime is refused.
- Until approval, signing in only explains the application status, and only to someone who knows the password.
