<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Reviews come only from customers who received the product, and an administrator can hide one. */
class ProductReviewTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $this->customer->company->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => true]]]);
    }

    private function completeOrder(): void
    {
        $id = $this->order(false);
        Order::withoutGlobalScopes()->whereKey($id)->update(['status' => 'completed']);
    }

    public function test_only_a_customer_with_a_completed_order_can_review_and_only_once(): void
    {
        $url = "/api/products/{$this->product->id}/review";

        // No order yet: not eligible, and a review is refused.
        $this->actingAs($this->customer, 'sanctum')->getJson($url)->assertOk()->assertJsonPath('eligible', false);
        $this->postJson($url, ['rating' => 5])->assertForbidden();

        $this->completeOrder();
        $this->actingAs($this->customer, 'sanctum')->getJson($url)->assertJsonPath('eligible', true);
        $this->postJson($url, ['rating' => 6])->assertStatus(422);
        $this->postJson($url, ['rating' => 5, 'comment' => '<b>Great</b> work'])->assertCreated();

        // A second post edits the same review instead of adding another.
        $this->postJson($url, ['rating' => 4, 'comment' => 'Good'])->assertOk();
        $this->assertSame(1, ProductReview::count());
        $this->assertSame(4, ProductReview::first()->rating);
    }

    public function test_the_public_product_page_shows_the_average_and_hidden_reviews_disappear(): void
    {
        $this->completeOrder();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/products/{$this->product->id}/review", ['rating' => 4, 'comment' => 'Solid'])->assertCreated();
        $page = "/api/public/storefronts/{$this->customer->company->slug}/products/{$this->product->id}";

        $reviews = $this->getJson($page)->assertOk()->json('reviews');
        $this->assertSame(1, $reviews['count']);
        $this->assertSame(4.0, (float) $reviews['average']);
        $this->assertArrayNotHasKey('email', $reviews['items'][0]);

        $id = ProductReview::first()->id;
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/reviews/{$id}/moderate", ['status' => 'hidden'])->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/reviews/{$id}/moderate", ['status' => 'hidden'])->assertOk();

        // The public page is cached for a minute in browsers, not on the server, so it updates at once here.
        $this->assertSame(0, $this->getJson($page)->json('reviews.count'));
    }

    public function test_product_cards_carry_the_stars_for_the_listing(): void
    {
        $list = fn () => $this->getJson("/api/public/storefronts/{$this->customer->company->slug}/products")->assertOk()->json('data.0.rating');
        $this->assertSame(['count' => 0, 'average' => null], $list());

        $this->completeOrder();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/products/{$this->product->id}/review", ['rating' => 4, 'comment' => 'Solid'])->assertCreated();
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertEquals(['count' => 1, 'average' => 4.0], $list());
    }
}
