<?php

namespace App\Products\Armorhub\Blueprints;

/** The launch bundles ArmorHub ships. Each is data: new ones can be added without touching the core or these classes' logic. */
final class ArmorhubBlueprints
{
    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return [
            self::automotive('automotive-egypt', 'Automotive protection: Egypt', 'EG', 'EGP', 13),
            self::automotive('automotive-saudi', 'Automotive protection: Saudi Arabia', 'SA', 'SAR', 1),
            [
                'id' => 'armorhub.home-services', 'name' => 'Home services marketplace', 'version' => 1,
                'description' => 'A home-services market with a storefront, and a friendlier cancellation policy: customers can cancel until the appointment is scheduled.',
                'params' => ['name' => 'string', 'country' => 'string'],
                'steps' => [
                    ['id' => 'market', 'type' => 'armorhub.market', 'args' => ['name' => '{{name}}', 'industry' => 'home-services', 'country' => '{{country}}']],
                    ['id' => 'store', 'type' => 'armorhub.store', 'args' => ['name' => '{{name}}', 'headline' => 'Trusted home services, quoted by experts near you', 'subheadline' => 'Cleaning, AC, plumbing and more.', 'color' => '#054d28']],
                    ['type' => 'workflow.override', 'args' => ['key' => 'armorhub.order', 'changes' => [['transition' => 'cancel', 'from' => ['pending_quotes', 'quotes_received', 'partner_selected', 'awaiting_payment', 'paid']]]]],
                ],
            ],
        ];
    }

    /** Amounts are in the country's currency: $scale converts the Saudi price list. */
    private static function automotive(string $slug, string $name, string $country, string $currency, int $scale): array
    {
        $price = fn (int $sar) => $sar * $scale;

        return [
            'id' => "armorhub.{$slug}", 'name' => $name, 'version' => 1,
            'description' => "An automotive paint-protection marketplace for {$country}, priced in {$currency}, with a storefront and starter products.",
            'params' => ['name' => 'string'],
            'steps' => [
                ['id' => 'market', 'type' => 'armorhub.market', 'args' => ['name' => '{{name}}', 'industry' => 'automotive', 'country' => $country]],
                ['id' => 'store', 'type' => 'armorhub.store', 'args' => ['name' => '{{name}}', 'headline' => 'Protect your car like it matters', 'subheadline' => 'Paint protection film, ceramic coating and tint, fitted by certified installers near you.', 'color' => '#163300']],
                ['type' => 'armorhub.catalog', 'args' => ['type' => 'vehicle_part', 'items' => [
                    ['name' => 'Hood', 'slug' => 'hood', 'coverage_factor' => 1.0], ['name' => 'Bumper', 'slug' => 'bumper', 'coverage_factor' => 0.8], ['name' => 'Mirrors', 'slug' => 'mirrors', 'coverage_factor' => 0.3],
                ]]],
                ['type' => 'armorhub.products', 'args' => ['items' => [
                    ['name' => 'Paint Protection Film', 'brand' => 'Premium', 'category' => 'paint-protection-film', 'base_price' => $price(500), 'warranty_years' => 10, 'rules' => [
                        ['target_type' => 'vehicle_part', 'target' => 'Hood', 'price' => $price(1200), 'duration_minutes' => 180],
                        ['target_type' => 'vehicle_part', 'target' => 'Bumper', 'price' => $price(900), 'duration_minutes' => 120],
                        ['target_type' => 'vehicle_part', 'target' => 'Mirrors', 'price' => $price(250), 'duration_minutes' => 45],
                    ]],
                    ['name' => 'Ceramic Coating', 'brand' => 'Premium', 'category' => 'ceramic-coating', 'base_price' => $price(400), 'warranty_years' => 5, 'rules' => [
                        ['target_type' => 'vehicle_part', 'target' => 'Hood', 'price' => $price(600), 'duration_minutes' => 120],
                        ['target_type' => 'vehicle_part', 'target' => 'Bumper', 'price' => $price(450), 'duration_minutes' => 90],
                    ]],
                ]]],
            ],
        ];
    }
}
