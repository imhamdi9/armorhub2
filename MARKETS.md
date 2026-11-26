# Markets (countries)

ArmorHub is built to run in several countries at once. Everything that differs between countries lives in
**market packs** in `apps/api/config/markets.php`. Saudi Arabia (`SA`) and Egypt (`EG`) ship first.

## What a pack holds

| | Saudi Arabia | Egypt |
|---|---|---|
| Currency | SAR (ر.س) | EGP (ج.م) |
| VAT | 15%, prices include VAT | 14%, prices include VAT |
| Mobile numbers | `+966 5X XXX XXXX` | `+20 1X XXXX XXXX` |
| Tax ID shape | 15 digits, starts and ends with 3 | 9 digits |
| Weekend | Friday–Saturday | Friday–Saturday |
| Suggested payments | Moyasar, Tap, Stripe | Paymob, Fawry, Stripe |
| E-invoicing | ZATCA (not built yet) | ETA (not built yet) |

## How it works

- A **tenant** has a country (Admin → Tenants). Its stores, orders and customers follow that country.
  A tenant with no country uses the **default market** (Admin → Settings → Platform), and if there is
  none, the single global currency (`platform.currency`), which keeps older installs working.
- **Currency** comes from the country: prices on storefronts, quotes, orders and payments are all in it.
- **VAT** is worked out per order from the country's rate and stored on the order (`tax_rate`, `tax_amount`)
  when it is priced and again when a quote is accepted, so later rate changes never rewrite old orders.
  Customers see "Includes VAT (15%)" on their order.
- **Products can be limited to a country** (`products.market`), because prices are in that country's
  currency. A product with no country is offered everywhere. Create one product per country when prices differ.
- **Phone numbers** typed at signup are checked against the store's country and saved as `+9665…` / `+201…`.
  Arabic digits and `05…` / `00966…` forms are accepted.
- The public API `GET /api/public/markets` lists the countries; `GET /api/public/storefronts/{slug}` includes the store's market.

## Adding a country

1. Add an entry to `config/markets.php` (copy `SA`; set currency, VAT, phone pattern, cities, languages).
2. Add its code and name to the country list in `apps/web/src/app/(dashboard)/dashboard/tenants/page.tsx`
   and `components/public/platform-home.tsx`, and add the Arabic names to `lib/i18n-ar.ts`.
3. Add a payment driver if the country needs a local provider (see `app/Services/Payment/Providers`).
4. Assign a tenant to it in Admin → Tenants.

Adding an **industry** is separate (see the industry modules); a country and an industry combine freely.

## Not built yet (needed before charging real customers)

- **E-invoicing**: ZATCA (Saudi) and ETA (Egypt) require invoices to be reported to the tax authority in a set
  format. The VAT amounts are already recorded per order; the reporting integration is still to do.
- **Local payment drivers**: Moyasar, Tap, Paymob and Fawry. Stripe and the custom HTTP gateway work today.
- Per-country legal pages (terms, privacy) and SMS/WhatsApp number verification.
