<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\User;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Photos on reviews, questions the seller answers, and the delivery estimate on the product page. */
class ReviewPhotosAndQuestionsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private Product $goods;
    private string $page;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        Storage::fake(config('platform.media_disk'));
        $store = $this->customer->company;
        $store->update(['status' => 'active', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'gulf@parts.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
        $this->goods = Product::create(['name' => 'Brake Pads', 'slug' => 'brake-pads-q', 'seller_id' => $co->id, 'base_price' => 100, 'is_active' => true, 'requires_shipping' => true,
            'industry_id' => $this->product->industry_id, 'category' => 'spare-parts']);
        $this->page = "/api/public/storefronts/{$store->slug}/products/{$this->goods->id}";
    }

    private function received(): void
    {
        Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $this->customer->id, 'product_id' => $this->goods->id, 'status' => 'completed',
            'product_amount' => 100, 'total_amount' => 100, 'currency' => 'USD']);
    }

    public function test_a_review_carries_the_buyers_own_photos_and_the_page_shows_them(): void
    {
        $url = "/api/products/{$this->goods->id}";
        $img = fn () => UploadedFile::fake()->image('p.jpg', 400, 300);

        $this->actingAs($this->customer, 'sanctum')->post("{$url}/review-photos", ['image' => $img()], ['Accept' => 'application/json'])->assertForbidden();   // not received yet
        $this->received();
        $path = $this->post("{$url}/review-photos", ['image' => $img()], ['Accept' => 'application/json'])->assertCreated()->json('path');
        $this->assertMatchesRegularExpression('#^/media/[0-9a-f-]{36}\.jpg$#', $path);

        $this->postJson("{$url}/review", ['rating' => 5, 'comment' => 'As described', 'photos' => ['/media/'.str_repeat('a', 36).'.jpg']])->assertUnprocessable();   // not theirs
        $this->postJson("{$url}/review", ['rating' => 5, 'photos' => ['https://evil.example/x.jpg']])->assertUnprocessable();                               // only our own media
        $this->postJson("{$url}/review", ['rating' => 5, 'comment' => 'As described', 'photos' => [$path]])->assertCreated()->assertJsonPath('photos.0', $path);

        $this->assertSame([$path], $this->getJson("{$url}/review")->json('review.photos'));
        $this->assertSame([$path], $this->getJson($this->page)->assertOk()->json('reviews.items.0.photos'));

        // Another customer cannot attach someone else's photo to their own review.
        $other = User::factory()->create(['company_id' => $this->customer->company_id]);
        $other->assignRole('customer');
        Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $other->id, 'product_id' => $this->goods->id, 'status' => 'completed', 'product_amount' => 1, 'total_amount' => 1, 'currency' => 'USD']);
        $this->actingAs($other, 'sanctum')->postJson("{$url}/review", ['rating' => 1, 'photos' => [$path]])->assertUnprocessable();
    }

    public function test_questions_are_answered_by_the_seller_and_only_answered_ones_are_public(): void
    {
        $ask = fn (string $q) => $this->actingAs($this->customer, 'sanctum')->postJson("/api/products/{$this->goods->id}/questions", ['question' => $q]);

        $ask('Short')->assertUnprocessable();
        $id = $ask('Does it fit a 2015 Corolla?')->assertCreated()->json('id');
        $this->assertSame([], $this->getJson($this->page)->json('questions'));                                       // not public until answered
        $this->assertSame(1, $this->seller->notifications()->count());                                              // the seller is told

        $this->actingAs($this->customer, 'sanctum')->putJson("/api/product-questions/{$id}/answer", ['answer' => 'Yes'])->assertNotFound();      // the asker cannot answer
        $rival = User::factory()->create(['email' => 'rival@parts.example.org', 'company_id' => Company::create(['tenant_id' => $this->customer->company->tenant_id, 'name' => 'Rival', 'type' => 'supplier', 'status' => 'active'])->id]);
        $rival->assignRole('seller');
        $this->actingAs($rival, 'sanctum')->putJson("/api/product-questions/{$id}/answer", ['answer' => 'Yes'])->assertNotFound();

        $inbox = $this->actingAs($this->seller, 'sanctum')->getJson('/api/product-questions')->assertOk()->json();
        $this->assertSame('Does it fit a 2015 Corolla?', $inbox[0]['question']);
        $this->putJson("/api/product-questions/{$id}/answer", ['answer' => '<b>Yes</b>, it fits 2014 to 2019.'])->assertOk()->assertJsonPath('answer', 'Yes, it fits 2014 to 2019.');

        $public = $this->getJson($this->page)->json('questions');
        $this->assertSame('Does it fit a 2015 Corolla?', $public[0]['question']);
        $this->assertSame('Yes, it fits 2014 to 2019.', $public[0]['answer']);
        $this->assertTrue($this->customer->notifications()->get()->contains(fn ($n) => str_contains($n->data['title'], 'answered')));   // and so is the asker

        $this->actingAs($this->customer, 'sanctum')->putJson("/api/product-questions/{$id}/status", ['status' => 'hidden'])->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->putJson("/api/product-questions/{$id}/status", ['status' => 'hidden'])->assertOk();
        $this->assertSame([], $this->getJson($this->page)->json('questions'));
    }

    public function test_questions_are_rate_limited_per_person(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($this->customer, 'sanctum')->postJson("/api/products/{$this->goods->id}/questions", ['question' => "Question number {$i} about it"])->assertCreated();
        }
        $this->postJson("/api/products/{$this->goods->id}/questions", ['question' => 'One question too many'])->assertStatus(429);
        $this->assertSame(5, ProductQuestion::count());
    }

    public function test_the_page_estimates_delivery_from_the_markets_days_and_who_ships(): void
    {
        $this->assertNull($this->getJson($this->page)->json('delivery_estimate'));                                  // the market has not said how long it takes

        \App\Models\Tenant::whereKey($this->customer->company->tenant_id)->update(['market' => 'SA']);
        app(Settings::class)->set('shipping.eta_days.SA', 3);
        \Illuminate\Support\Facades\Cache::flush();

        $est = $this->getJson($this->page)->json('delivery_estimate');
        $this->assertSame([3, 5], [$est['min_days'], $est['max_days']]);                                              // three days of travel plus two to pack
        $this->assertSame(now()->addDays(3)->toDateString(), $est['from']);

        $this->goods->update(['fulfilment' => 'armorhub']);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertSame([3, 3], [$this->getJson($this->page)->json('delivery_estimate.min_days'), $this->getJson($this->page)->json('delivery_estimate.max_days')]);   // ArmorHub ships at once
    }
}
