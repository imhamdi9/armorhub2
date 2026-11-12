# Installation help by centres — decisions, design and status

Goods can be bought on their own. A product's `installation` is `none`, `optional` or `required`; either way it is sold alone. If the
customer wants help, they ask for installation / repair / maintenance and nearby centres answer. Customers and centres stay on the
platform because: money is held by the platform until the job is proven, the warranty only exists for jobs done through it, and
contact details are never shared before payment (and real phone numbers never).

## Decisions (from the product owner)
| Decision | Value | Where to change it |
|---|---|---|
| Platform fee | **5%** of the centre's price by default, **set by hand per market/country**; empty = use the all-markets value | Admin → Settings → "Installation by centres" (`installation.fee_percent`, `installation.fee_percent.<CODE>`) |
| Customer objection window | **48 hours** after the centre's proof | `installation.objection_hours` |
| Dispute | Opening one **stops the release**; money returns only after the platform checks both sides' evidence (was it done? defects such as bad fitting, leak, maintenance/repair) | Admin → Disputes |
| Customer's contact | A **temporary substitute number** (platform number + extension). Real numbers are never shown | `installation.proxy_number` |
| Centre withdrawals | **Bank transfer or mobile wallet**; the platform pays outside the app and records the reference | Admin → Withdrawals |
| Locations | Taken from the device after an explicit permission prompt, **never typed** (customer, and optionally the centre on proof) | — |
| Warranty wording | Platform warranty only for work arranged and paid on the platform; the platform is not responsible for anything outside it. The customer accepts it before paying (recorded with a version) | `InstallationJobs::terms()` — **have it reviewed legally before launch** |
Also configurable: offers stay open 48 h, platform warranty 180 days, minimum withdrawal 100, loyalty points per currency unit and value per point,
fee reductions for silver/gold centres.

## The journey
1. Product page (optional/required products) → "Ask centres for fitting offers", or Installation requests → New request.
2. Request: what to do + vehicle/device + **allowed location**. Free text is checked for phone numbers, e-mails, links and messaging apps.
3. Centres within range (same city, or up to 100 km) are notified and see only: what, vehicle/device, city, rounded distance. They answer with price, days, note.
4. The customer sees offers ranked **by distance band** (same city or ≤10 km, ≤25, ≤50, ≤100 km) and, inside a band, by **quality** (rating 50%, answer speed 25%, how often the
   centre answers 25%; tier bonus; lost disputes and strikes cost points), then price. Each offer shows a code (`C-4F2A`), tier, rating, jobs done, answer speed, rounded
   distance, price, days and badges (closest, fastest, best rated). Centres with 5+ ratings averaging under 3 are not offered the job.
5. The customer accepts (terms recorded, optional loyalty points) and pays through the platform's payment providers. The centre's share (price − fee) is **held**.
6. After payment: the customer gets the centre's name, address, Google Maps link and the substitute number; the centre gets the customer's name, Google Maps link of the
   allowed location and the substitute number.
7. The centre starts the job and sends proof: before photos, after photos and a serial/warranty number (photos are private, random names, served only to the two sides and admins;
   photos taken >2 km from the job or not recent are flagged). Complete proof starts the 48-hour window.
8. The customer confirms (released at once), disputes (held), or does nothing (released automatically after the window; done by the hourly job and also whenever people look,
   because free hosts have no scheduler). Release makes the money **available** in the centre's wallet, starts the warranty, gives the customer loyalty points and asks for a rating.
9. The centre withdraws (bank/wallet); an admin pays and records the reference.
10. Dispute: either side opens it with a type; both add evidence; an admin decides: release to the centre, partial refund, or full refund. Refunds are paid by the admin outside the app and the
    reference recorded. A refund counts as a lost dispute for the centre.
11. Off-platform dealing: the customer can report it; an admin confirms (a strike) or dismisses. 3 strikes suspend the centre.

## Rating and loyalty engine (`App\Services\Reputation`)
- Centre record rebuilt from facts, never typed: rating (customer reviews of released jobs), jobs done, answer speed (last 50 offers), answer rate (last 30 days of invitations, needs ≥5),
  lost disputes, strikes. Tier: new → bronze (3 jobs) → silver (15 jobs, ≥8 ratings, ≥4.3, ≤5% lost) → gold (50 jobs, ≥20 ratings, ≥4.7, ≤2% lost). Silver/gold pay a smaller platform fee.
- Customers earn points per currency unit paid (on release) and +5 for a rating. Points become a discount at accept time, paid by the platform from its own fee (never more than the fee).
- Rebuilt when an offer is sent, a review is left, a job is released or a dispute is decided, and hourly by `php artisan installations:maintain`.

## Payments
Installation jobs reuse the configured payment providers (simulated for demos, Stripe, custom HTTP): a job is paid under the **negative id** of its request, so the existing
checkout and webhook code is reused. A webhook pays a job only for the exact amount and currency, and only once.

## Honest limits (to decide before launch)
- **Substitute number:** the app issues the extension and shows the platform number; **bridging calls needs a telephony provider** (Twilio, a local SIP/IVR provider). Until one is connected,
  set `installation.proxy_number` to a number you operate, or rely on the notifications.
- **Refunds and withdrawals** are recorded in the app but paid by an admin outside it (bank transfer / provider dashboard).
- **Warranty claims after release** are not a self-service flow yet (a dispute can only be opened while the money is held); handle them through support.
- Proof photos can be faked; geotag/time checks only flag them. Customer confirmation and the dispute process are the real protection.
- Ratings, response time and tier start empty for new centres (treated as average).
- The platform fee is not subject to VAT logic; the centre's price is treated as final.
