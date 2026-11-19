<?php

namespace Tests\Feature;

use App\Models\LoyaltyEntry;
use App\Models\Order;
use App\Models\ProductReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A customer can take their data with them; nobody else's data and no secret comes along. */
class DataExportTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    public function test_a_customer_downloads_their_own_data_and_nothing_secret_or_foreign(): void
    {
        $this->buildMarketplace();
        $order = Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $this->customer->id, 'product_id' => $this->product->id, 'status' => 'completed',
            'product_amount' => 100, 'total_amount' => 100, 'currency' => 'EGP', 'gift_note' => 'Happy birthday', 'shipping_address' => ['name' => 'Layla', 'line1' => '12 King Road', 'city' => 'Cairo']]);
        ProductReview::create(['product_id' => $this->product->id, 'user_id' => $this->customer->id, 'rating' => 5, 'comment' => 'Great', 'status' => 'published']);
        LoyaltyEntry::create(['user_id' => $this->customer->id, 'points' => 40, 'reason' => 'goods_order', 'ref' => 'order:1']);

        // Someone else's order and review must not appear.
        $other = User::factory()->create(['company_id' => $this->customer->company_id]);
        $other->assignRole('customer');
        Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $other->id, 'product_id' => $this->product->id, 'status' => 'completed', 'product_amount' => 9, 'total_amount' => 9, 'currency' => 'EGP']);
        ProductReview::create(['product_id' => $this->product->id, 'user_id' => $other->id, 'rating' => 1, 'comment' => 'Someone else', 'status' => 'published']);

        $res = $this->actingAs($this->customer, 'sanctum')->getJson('/api/me/export')->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $data = $res->json();

        $this->assertSame($this->customer->email, $data['profile']['email']);
        $this->assertCount(1, $data['orders']);
        $this->assertSame($order->id, $data['orders'][0]['id']);
        $this->assertSame('Happy birthday', $data['orders'][0]['gift_note']);
        $this->assertSame('12 King Road', $data['orders'][0]['delivery_address']['line1']);
        $this->assertSame([['product' => $this->product->name, 'rating' => 5, 'comment' => 'Great', 'photos' => [], 'date' => now()->toDateString()]], $data['reviews']);
        $this->assertSame(40, $data['loyalty_points']['balance']);

        $raw = $res->getContent();
        $this->assertStringNotContainsString('Someone else', $raw);
        $this->assertStringNotContainsString($other->email, $raw);
        $this->assertStringNotContainsString('"password"', $raw);
        $this->assertStringNotContainsString($this->customer->password, $raw);

        $this->assertDatabaseHas('audit_logs', ['action' => 'account.exported']);
    }

    public function test_staff_and_guests_cannot_use_it_and_it_is_rate_limited(): void
    {
        $this->buildMarketplace();
        $this->getJson('/api/me/export')->assertUnauthorized();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/me/export')->assertUnprocessable();

        $this->actingAs($this->customer, 'sanctum');
        for ($i = 0; $i < 3; $i++) {
            $this->getJson('/api/me/export')->assertOk();
        }
        $this->getJson('/api/me/export')->assertStatus(429);
    }
}
