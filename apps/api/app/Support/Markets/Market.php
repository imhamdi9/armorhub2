<?php

namespace App\Support\Markets;

/** One country the platform operates in, read from config/markets.php. Immutable. */
final class Market
{
    /** @param array<string, mixed> $data */
    public function __construct(public readonly string $code, private array $data)
    {
    }

    public function name(string $lang = 'en'): string
    {
        return $this->data['name'][$lang] ?? $this->data['name']['en'];
    }

    public function currency(): string
    {
        return strtoupper($this->data['currency']);
    }

    public function vatRate(): float
    {
        return (float) ($this->data['vat']['rate'] ?? 0);
    }

    public function pricesIncludeVat(): bool
    {
        return (bool) ($this->data['vat']['inclusive'] ?? true);
    }

    /** @return list<string> */
    public function cities(): array
    {
        return $this->data['cities'] ?? [];
    }

    /**
     * The VAT contained in (or to be added to) an amount. With VAT-inclusive prices the order total is
     * unchanged and this is the tax portion of it; otherwise it is added on top.
     *
     * @return array{rate: float, net: float, tax: float, gross: float}
     */
    public function vat(float $amount): array
    {
        $rate = $this->vatRate();

        if ($this->pricesIncludeVat()) {
            $tax = round($amount * $rate / (100 + $rate), 2);

            return ['rate' => $rate, 'net' => round($amount - $tax, 2), 'tax' => $tax, 'gross' => round($amount, 2)];
        }

        $tax = round($amount * $rate / 100, 2);

        return ['rate' => $rate, 'net' => round($amount, 2), 'tax' => $tax, 'gross' => round($amount + $tax, 2)];
    }

    /** The tax registration number is checked for shape only; the tax authority is the source of truth. */
    public function validTaxId(string $id): bool
    {
        $pattern = $this->data['vat']['id_pattern'] ?? null;

        return $pattern === null || preg_match($pattern, preg_replace('/\s+/', '', $id)) === 1;
    }

    /**
     * Normalises a phone number to E.164 (+9665XXXXXXXX). Accepts 05…, 5…, 009665…, +9665…, spaces,
     * dashes and Arabic-Indic digits. Returns null when it is not a valid number for this country.
     */
    public function normalizePhone(string $input): ?string
    {
        $digits = strtr($input, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        $digits = preg_replace('/[^\d+]/', '', $digits) ?? '';
        $dial = $this->data['phone']['dial'];

        $digits = ltrim($digits, '+');
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }
        if (str_starts_with($digits, $dial)) {
            $digits = substr($digits, strlen($dial));
        }
        $digits = ltrim($digits, '0');

        return preg_match($this->data['phone']['pattern'], $digits) === 1 ? '+'.$dial.$digits : null;
    }

    /** What the public site and the apps need to know. @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'name' => $this->data['name'],
            'currency' => $this->currency(),
            'currency_symbol' => $this->data['currency_symbol'],
            'decimals' => $this->data['decimals'],
            'locale' => $this->data['locale'],
            'languages' => $this->data['languages'],
            'timezone' => $this->data['timezone'],
            'weekend' => $this->data['weekend'],
            'vat' => ['rate' => $this->vatRate(), 'inclusive' => $this->pricesIncludeVat(), 'label' => $this->data['vat']['label'], 'id_label' => $this->data['vat']['id_label']],
            'phone' => ['dial' => $this->data['phone']['dial'], 'example' => $this->data['phone']['example']],
            'cities' => $this->cities(),
        ];
    }

    /** Providers recommended for this country. @return list<string> */
    public function recommendedPayments(): array
    {
        return $this->data['payments'] ?? [];
    }
}
