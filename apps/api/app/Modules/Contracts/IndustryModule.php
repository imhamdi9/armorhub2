<?php

namespace App\Modules\Contracts;

/**
 * An industry plug-in (automotive, home services, beauty, devices...).
 *
 * The platform core (tenants, companies, partners, products, orders,
 * quotes, payments, commissions) is industry-neutral. Everything that is
 * specific to one vertical lives in a module implementing this contract.
 */
interface IndustryModule
{
    /** Stable identifier; matches industries.key. */
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /**
     * Models a customer brings to be serviced (vehicle, property, device).
     * Keyed by the morph alias stored in orders.subject_type.
     *
     * @return array<string, class-string>
     */
    public function subjectModels(): array;

    /**
     * Models a product price rule can target (vehicle part, room, ...).
     * Keyed by the morph alias stored in product_price_rules.target_type.
     *
     * @return array<string, class-string>
     */
    public function targetModels(): array;

    /**
     * Gate policies, model class => policy class.
     *
     * @return array<class-string, class-string>
     */
    public function policies(): array;

    /** Absolute path to an API routes file, or null. */
    public function routesFile(): ?string;

    /**
     * Industry-specific permission names to seed.
     *
     * @return list<string>
     */
    public function permissions(): array;

    /** Custom pricing, or null to use the default sum-of-rules strategy. */
    public function pricingStrategy(): ?PricingStrategy;
}
