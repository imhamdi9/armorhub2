<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The terms a partner sees before joining are the live settings, with totals only. */
class PublicTermsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_terms_show_the_live_commission_and_how_it_is_shared(): void
    {
        $this->buildMarketplace();

        $json = $this->getJson('/api/public/terms')->assertOk()->json();

        $this->assertEqualsWithDelta(1.0, array_sum($json['split']), 0.0001);
        $this->assertSame(['platform', 'company', 'partner'], array_keys($json['split']));
        $this->assertIsInt($json['open_requests']);
        $this->assertIsInt($json['verified_partners']);
        $this->assertArrayNotHasKey('orders', $json);
    }
}
