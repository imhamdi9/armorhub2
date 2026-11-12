# Platform architecture

**ArmorHub is the first product built on this platform. It must never be the platform.**
In five years the same platform should run a different business with the same workflow concept (request → quote →
agree → pay → fulfil → warranty/follow-up) by **configuration, plugin, integration, extension or module, without editing the core.**

## Principles

1. **Core is business-neutral.** It knows tenants, identity, events, workflows, integrations, configuration and modules.
   It does not know cars, quotes, shipments or ArmorHub.
2. **Zero core modification.** A new capability is added by a module, a plugin, an integration, a configuration or an
   extension point. If adding something needs a core edit, the core is missing an extension point: add the *point*, once.
   This is enforced by tests (`tests/Architecture`), not by good intentions.
3. **Event-driven.** State changes are published as versioned domain events. Everything reactive (notifications, search,
   analytics, integrations, projections) is a subscriber. Nothing calls those things directly.
4. **Integration-first.** Every external dependency sits behind a *capability port* (a contract the business owns).
   Providers are adapters, chosen by configuration per tenant. No business rule may name a vendor.
5. **Configuration over code.** Differences between tenants, markets, industries and products are data
   (settings, workflow definitions, market packs, role definitions), versioned and auditable.
6. **Multi-tenant by construction.** Every event, record, setting, role and integration is tenant-scoped.
7. **Earn abstractions.** An abstraction needs two real users before it is generalised. The "second product test"
   (below) is the proof, not speculation.

## Layers

```
 Products      ArmorHub (first)  ·  <future products>        ← modules: events, workflows, roles, settings, UI
 ──────────────────────────────────────────────────────────
 Capability ports   Payments · Shipping · Messaging · Storage · Identity providers ...   ← contracts, vendor-free
 Adapters           Stripe · custom HTTP · SMSA · S3 · ...                                ← replaceable, configured
 ──────────────────────────────────────────────────────────
 Core          Events · Modules · Workflow · Config · Identity · Tenancy · Integration · Audit
```
Dependencies point **downward only**. Core never imports a product; a product never reaches into another product.

## Core domains (what the platform owns)

| Domain | Responsibility | State |
|---|---|---|
| **Event infrastructure** | Event catalog, transactional outbox, subscribers, retry, dead-letter, replay, correlation | **M1 (built)** |
| **Module system** | Manifest, dependencies, core-version compatibility, registration, per-tenant enablement | **M1 (built)** |
| **Workflow foundation** | Workflows as data: states, transitions, guards, actions, per-tenant versions | **M2 (built)** |
| **Integration foundation** | Capability ports, connector registry, outbound webhooks, inbound endpoints, mapping, delivery log | M3 (payments/shipping already follow the pattern) |
| **Configuration engine** | Typed, versioned, tenant/market-scoped config with schema, history, rollback | M4 (settings + market packs exist) |
| **Identity** | Roles and permissions as definitions contributed by modules; SSO/OIDC provider port | M5 |
| **Multi-tenant foundation** | Tenant context, isolation enforced below the application code, per-tenant module/config | M6 |
| **Plugin foundation** | Third-party packaging (composer), discovery, permissions sandbox, signed manifests | M7 |

## Gap analysis: where the code is today

| Area | Today | Gap |
|---|---|---|
| Events | An order-timeline table plus a notifier called from a model hook | No bus, no catalog, no retry/replay; the notifier is hard-wired |
| Workflow | `OrderStatus` enum + `OrderLifecycleService` (hard-coded) | Order lifecycle is code, not data |
| Integrations | Payment and shipping **drivers** with runtime config, SSRF guard, signed webhooks | Good pattern; not generic; no outbound webhooks/mapping |
| Config | `Settings` (typed, encrypted secrets, audit) + market packs | Not versioned or tenant-scoped |
| Identity | Roles/permissions seeded in one PHP file | Not contributed by modules; no SSO port |
| Tenancy | `tenant_id` + company scope | Soft isolation in app code |
| Modules | `IndustryModule` registry (automotive, home, beauty, devices) | Good seed; no manifest, no lifecycle, no on/off per tenant |
| Boundaries | None enforced | Core and ArmorHub are intertwined |

## Roadmap (each milestone has an acceptance test)

| # | Milestone | Done when |
|---|---|---|
| **M1** | Event infrastructure + module system + boundary tests | A test-only second product publishes and consumes events without touching core; ArmorHub's notifications run as a subscriber |
| **M2 (done)** | Workflow engine; the order lifecycle becomes a workflow definition | The second product defines a different lifecycle by data only; ArmorHub's behaviour is unchanged |
| M3 | Integration foundation: capability ports, outbound webhooks (events → tenant endpoints), inbound endpoints, mapping | A tenant connects a new system by configuration; payments/shipping move onto it |
| M4 | Configuration engine: versioned, tenant-scoped, schema-validated, rollback | Any tenant setting can be changed, audited and rolled back |
| M5 | Identity: roles/permissions contributed by modules; OIDC provider port | A module ships its own roles with no seeder edit |
| M6 | Tenancy hardening: tenant context everywhere, isolation tests, per-tenant modules | A cross-tenant read is impossible by construction and proven by tests |
| M7 | Plugin packaging: composer discovery, signed manifests, capability permissions | A third party ships a module as a package |

## Editable surfaces (built)

"Everything editable by admins" is delivered as **governed editable surfaces**, not unbounded editing:

- A module *declares* what may be edited (`$context->surface(key, label, fields, ...)`): typed fields (text, longtext, bool, number, https url, #rrggbb colour, select, list), length limits, and whether the content is public.
- Admins edit a per-language (EN/AR) **draft**, **publish** it with a note, and can **restore** any earlier version. History is immutable; publishing is audited and emits `surface.published`.
- Validation lives in the core, so no field can carry markup or arbitrary code; unset fields fall back to the built-in wording.
- Security, payment secrets, roles and money logic are deliberately **not** surfaces.
- ArmorHub declares: `site.home` (wording, 14 section switches, FAQ, banner), `site.brand` (name, logo, colours), `site.navigation`, `app.sidebar`. A new product adds surfaces from its own module with no core change.

More surfaces: `app.messages` (notification and email wording with `{placeholders}`, English), `app.sidebar` (dashboard menu names, read by the signed-in sidebar), and the home page's promises, steps, use cases and "why us" sections.

Admin UI: Administration → Content.

### Theme editor

Administration → Theme editor is a visual editor on top of the same governed surfaces. A live preview (the real home page in a same-site frame, with Desktop/Tablet/Mobile widths) shows unsaved changes instantly; nothing reaches visitors until Publish. It edits: section order and visibility, accent/dark colours, headline/text fonts (a fixed list of safe stacks, no external font files), hero, banner and logo images (https addresses only), and the main wording. Everything is validated server-side, so no CSS, script or markup can be injected. Only `/theme-preview` may be framed, and only by the same site.

### Governed publishing, uploads and click-to-edit

- **Approval:** with the setting `content.require_approval` on (Settings → Content), Publish creates a request that a *different* platform admin must approve. A request is bound to the exact draft it was made for; editing the draft afterwards makes it *stale* instead of publishing unreviewed content. Restoring an earlier version stays immediate (it is the emergency undo) and is audited.
- **Scheduling:** any publish can carry a time. Due changes go live the next time the site reads content (at most one check per 15 seconds), so no scheduler is required; `surfaces:publish-due` (every minute in the schedule) does the same on hosts that have cron.
- **Uploads:** admins upload JPG/PNG/WebP/GIF (max 3 MB; never SVG) to the `MEDIA_DISK` (defaults to the documents disk; use an S3/R2 disk where there is no permanent disk). Files get random names and are served only through `/api/public/media/<name>` with `nosniff` and a sandbox CSP.
- **Click-to-edit:** in the theme editor's preview, clicking text opens its field and clicking a section selects it in the list.

### Egyptian demo data

`php artisan platform:demo-egypt` (idempotent; `--force` allows production) creates four Egyptian stores (cars, beauty, home, devices), partners in Cairo, Giza, Alexandria and Mansoura, six local sellers, customers, 28 products priced in EGP with galleries, a published Egyptian hero for the platform home page, and demo logins on `@demo.eg`. Images are flat illustrations generated by `apps/web/scripts/make-demo-art.mjs` and stored in `apps/api/database/demo-assets/egypt`; they are loaded into the media library on first run.

Real photos: the demo ships with illustrations. Replace them with your own photographs by following `docs/DEMO-PHOTOS.md` (a shot list per product) and running `php artisan platform:import-photos <folder>`, or upload per product from the product editor (**Upload a photo**, available to admins, store owners and sellers).

### Custom roles

Administration → Roles lets a platform admin create roles as bundles of everyday permissions. Built-in roles cannot be changed. Admin, delete, user-management and payment permissions are not grantable (privilege-escalation guard), custom-role holders must belong to a company (data scoping), and a role in use cannot be deleted. Creation, change and deletion are audited.

## Blueprints (built)

A **blueprint** is a launch bundle: the steps that turn an empty platform into a working business of one kind (a market for a
country, its storefront, starter products, policy changes). It is pure data. The core orchestrates (validates the operator's answers,
runs the steps in one transaction, records the result, announces `blueprint.applied`); modules supply the *step types*. ArmorHub ships
automotive (Egypt, Saudi Arabia) and home-services blueprints; the clinic fixture ships its own using one step of its own and the core's
`workflow.override` step. A new kind of business is a new blueprint, not new code in the core.

## The second-product test

The platform is only generic if a second, unrelated product can be built on it **by adding code outside the core**.
`tests/Architecture/SecondProductTest.php` defines a clinic-appointments module inside the test suite: its own events,
subscriber and enablement. It must work with zero changes under `app/Core`. Every milestone extends this fixture
(M2: its own workflow; M3: its own integration; M5: its own roles).

## Decisions taken by default (change them early, not late)

1. **Evolve this codebase** into a modular monolith (strangler pattern), not a rewrite. Services are extracted later only if scale demands it.
2. **In-process events with a transactional outbox** behind a transport port. A broker (Kafka, SQS, NATS) can be swapped in by
   writing a transport adapter; no producer or subscriber changes. (Free hosting has no broker.)
3. **Core lives in `app/Core`**, products in `app/Products/*`. Extraction to a versioned package happens when a second product repository exists.
4. **Plugin = module + manifest.** The same contract serves first-party products and third-party plugins.

See `docs/adr/` for the reasoning behind each.
