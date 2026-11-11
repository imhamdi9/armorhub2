<?php

namespace App\Support\Markets;

use App\Models\Company;
use App\Models\Tenant;
use App\Support\Settings\Settings;

/** Looks up the market packs and decides which one applies to a tenant or company. */
class Markets
{
    /** @var array<string, Market> */
    private array $markets = [];

    public function __construct(private Settings $settings)
    {
        foreach (config('markets.markets', []) as $code => $data) {
            $this->markets[strtoupper($code)] = new Market(strtoupper($code), $data);
        }
    }

    /** @return array<string, Market> */
    public function all(): array
    {
        return $this->markets;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_keys($this->markets);
    }

    public function get(?string $code): ?Market
    {
        return $code ? ($this->markets[strtoupper($code)] ?? null) : null;
    }

    /** The market used when a tenant has not chosen one; null means "no markets" (one global currency). */
    public function default(): ?Market
    {
        return $this->get((string) $this->settings->get('platform.default_market') ?: config('markets.default'));
    }

    public function forTenant(?Tenant $tenant): ?Market
    {
        return $this->get($tenant?->market) ?? $this->default();
    }

    public function forCompany(?Company $company): ?Market
    {
        return $this->forTenant($company?->tenant);
    }

    /**
     * The market and VAT breakdown to record on an order for this company and amount.
     *
     * @return array{market: ?string, tax_rate: float, tax_amount: float}
     */
    public function taxFor(?Company $company, float $amount): array
    {
        $market = $this->forCompany($company);
        $vat = $market?->vat($amount);

        return ['market' => $market?->code, 'tax_rate' => $vat['rate'] ?? 0.0, 'tax_amount' => $vat['tax'] ?? 0.0];
    }

    /** A phone number checked for the company's country; with no country, any plausible international number (7-15 digits). */
    public function phoneFor(?Company $company, string $input): ?string
    {
        if ($market = $this->forCompany($company)) {
            return $market->normalizePhone($input);
        }

        $clean = preg_replace('/[^\d+]/', '', $input) ?? '';

        return preg_match('/^\+?\d{7,15}$/', $clean) === 1 ? $clean : null;
    }

    /** Like phoneFor(), for someone who belongs to a marketplace (tenant) rather than a store. */
    public function phoneForTenant(?Tenant $tenant, string $input): ?string
    {
        if ($market = $this->forTenant($tenant)) {
            return $market->normalizePhone($input);
        }

        $clean = preg_replace('/[^\d+]/', '', $input) ?? '';

        return preg_match('/^\+?\d{7,15}$/', $clean) === 1 ? $clean : null;
    }

    /** The ISO currency orders are charged in for this company. */
    public function currencyFor(?Company $company): string
    {
        return $this->forCompany($company)?->currency() ?? strtoupper((string) $this->settings->get('platform.currency'));
    }
}
