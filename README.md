```markdown
# ArmorHub

ArmorHub is a SaaS marketplace platform connecting customers with professional service providers. It is industry-agnostic: automotive protection is the first industry module, and any business with the same concept (customer requests a service, nearby partners quote, order is paid, commissions are split) can be added as another module.

## Vision

Build a complete ecosystem for:

- Paint Protection Film (PPF)
- Ceramic Coating
- Window Tint
- Detailing
- Automotive Protection Services

## Industry modules

The core (tenants, companies, partners, products, orders, partner quotes,
payments, commissions) is industry-neutral. Everything vertical-specific
lives in `apps/api/app/Modules/<Industry>`:

- a class implementing `App\Modules\Contracts\IndustryModule` (extend `BaseIndustryModule`)
- its own models (the "subject" a customer brings, e.g. a vehicle; the "targets" a price rule applies to, e.g. a vehicle part), policies, routes and permissions
- an optional custom `PricingStrategy`

Register the module in `AppServiceProvider::MODULES` and run `IndustrySeeder`
to create its `industries` row. Tenants and products reference an industry;
orders point at their subject polymorphically (`subject_type`/`subject_id`).
Shipped: `automotive`.

## Architecture

ArmorHub follows:

- API First Architecture
- Domain Driven Design
- Multi Tenant SaaS Architecture

## Applications

```

apps/
├── api              Laravel Backend
├── customer-web     Customer Platform
├── provider-web     Service Provider Dashboard
└── admin            Administration Panel

```

## Packages

```

packages/
├── auth
├── companies
├── vehicles
├── catalog
├── pricing
├── orders
├── payments
├── bookings
├── warranties
└── shared

```

## Development

The project uses:

- Laravel
- Next.js
- Docker
- MySQL
- Redis
- Meilisearch

## License

Proprietary