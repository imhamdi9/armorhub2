# Installation help (centres) — design and status

Goods can be bought on their own. A product's `installation` is `none`, `optional` or `required`; either way the product is sold alone.
If the customer wants help, they ask for installation/repair/maintenance and centres answer. This keeps customers and centres on the
platform: money is held by the platform until the job is proven, and the warranty only exists for jobs done through it.

## Decisions (from the product owner)
- Platform fee: **5%** of the centre's price by default, **changeable by hand per market/country** (admin settings, group "installation":
  `installation.fee_percent` and `installation.fee_percent.<CODE>`; empty = use the default).
- Customer objection window: **48 hours** (`installation.objection_hours`). Money is **not** released if a dispute is opened; both sides
  are heard and the job is checked (done? defects such as bad fitting, leaks, repair/maintenance issues?).
- The customer gets a **temporary substitute number** for the centre/technician; the real number is never shown.
- Centres withdraw by **bank transfer or wallet**.
- Locations are never typed: the customer's (and the centre's) position is taken from the device after an explicit permission prompt.

## Privacy rules
- Before payment a centre sees: what must be done, the vehicle/device, the area (city) and a rounded distance. Never name, phone,
  exact place or coordinates. The customer sees: a meaningless alias (`C-4F2A`), rating, number of ratings, completed jobs, answer
  speed, rounded distance, price, delivery days. Never name, phone, address or coordinates.
- Free text in requests and offers is checked (`ContactGuard`): phone numbers (also Arabic digits), e-mails, links and messaging-app
  hints are refused.
- Distances are rounded (0.5 km steps under 5 km, whole km above) so several requests cannot triangulate an address.

## Ranking of offers
1. Distance band: same city or up to 10 km, then up to 25, 50 and 100 km.
2. Inside a band, quality (rating 50%, answer speed 25%, how often the centre answers 25%; unknown values count as average), then price.
3. Centres with 5+ ratings averaging under 3 are not offered the job.
Badges shown: closest, fastest, best rated.

## Phases
1. **Done (backend):** product flag, requests with location consent, anonymous centre view, offers, ranking, per-market fee settings.
   API: `POST/GET /api/installation-requests`, `GET /api/installation-requests/{id}` (ranked offers), `POST .../cancel`,
   `GET /api/partner/installation-requests`, `POST /api/partner/installation-requests/{id}/offer`.
2. Accept an offer and pay on the platform (funds held, fee computed from the market's rate), reveal both sides' details, substitute
   number, map link, platform-only warranty wording and recorded consent.
3. Proof of completion (in-app camera, time/place/serial), 48-hour objection window, release, centre wallet ledger, withdrawals.
4. Disputes (evidence from both sides, admin decision), violations of the off-platform rule.
Screens for customers and centres come after the API of each phase.
