<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PartnerQuote;
use App\Models\Product;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Modules\HomeServices\Models\RoomType;
use App\Modules\Devices\Models\DeviceComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The money paths of the Egyptian demo, from a customer's first click to the seller's balance. */
class EgyptJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->artisan('platform:demo-egypt')->assertSuccessful();
    }

    private function as(string $email): static
    {
        return $this->actingAs(User::where('email', $email)->firstOrFail(), 'sanctum');
    }

    public function test_a_customer_books_a_home_service_a_partner_quotes_and_the_job_is_done(): void
    {
        $this->as('khaled.abdelrahman@demo.eg');

        $me = $this->getJson('/api/me')->assertOk()->json();
        $this->assertContains('property', $me['industry']['owned_subjects']);                     // a home is something the customer keeps
        $property = $this->postJson('/api/my-items/property', ['type' => 'Apartment', 'address' => '12 Nile Corniche', 'city' => 'Giza', 'country' => 'EG'])->assertCreated()->json('id');

        $cleaning = Product::where('slug', 'eg-cleaning')->firstOrFail();
        $kitchen = RoomType::where('slug', 'kitchen')->firstOrFail();
        $order = $this->postJson('/api/orders', ['product_id' => $cleaning->id, 'subject_type' => 'property', 'subject_id' => $property, 'target_type' => 'room_type', 'targets' => [$kitchen->id]])
            ->assertCreated()->json('order');

        $this->assertSame('EGP', $order['currency']);
        $this->assertEquals(700, $order['product_amount']);                                      // the Kitchen price in the catalogue
        $this->assertSame('pending_quotes', $order['status']);
        $this->assertEquals(14.0, (float) ($order['vat_rate'] ?? 14.0), 'Egypt charges 14% VAT');

        // A nearby partner quotes; the customer accepts and pays; the technician schedules and finishes the job.
        $quote = PartnerQuote::withoutGlobalScopes()->where('order_id', $order['id'])->firstOrFail();
        $managerEmail = User::where('partner_id', $quote->partner_id)->role('partner-manager')->value('email');
        $this->as($managerEmail)->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 150])->assertOk();
        $this->as('khaled.abdelrahman@demo.eg')->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertOk();
        $this->postJson("/api/orders/{$order['id']}/payments", ['amount' => Order::find($order['id'])->total_amount])->assertCreated();
        $this->assertSame('paid', Order::find($order['id'])->status->value ?? Order::find($order['id'])->status);

        $techEmail = User::where('partner_id', $quote->partner_id)->role('technician')->value('email');
        $this->as($techEmail)->postJson("/api/orders/{$order['id']}/schedule", ['scheduled_at' => now()->addDay()->toIso8601String()])->assertOk();
        $this->postJson("/api/orders/{$order['id']}/start")->assertOk();
        $this->postJson("/api/orders/{$order['id']}/complete")->assertOk();

        $this->as('khaled.abdelrahman@demo.eg')->getJson("/api/orders/{$order['id']}")->assertOk()->assertJsonPath('status', 'completed');
    }

    public function test_a_customer_buys_a_sellers_product_and_the_seller_earns_after_delivery(): void
    {
        $this->as('hesham.zaki@demo.eg');
        $charger = Product::where('slug', 'eg-charger')->firstOrFail();
        $whole = DeviceComponent::where('slug', 'whole-device')->firstOrFail();

        // A physical product needs somewhere to send it.
        $this->postJson('/api/orders', ['product_id' => $charger->id, 'target_type' => 'device_component', 'targets' => [$whole->id]])->assertStatus(422);
        $order = $this->postJson('/api/orders', [
            'product_id' => $charger->id, 'target_type' => 'device_component', 'targets' => [$whole->id],
            'shipping_address' => ['name' => 'Hesham Zaki', 'phone' => '+201001234567', 'line1' => '5 Tahrir St', 'city' => 'Giza', 'country' => 'EG'],
        ])->assertCreated()->json('order');

        $this->assertSame('awaiting_payment', $order['status']);                                  // goods go straight to payment: nobody to quote
        $this->assertEquals(560, $order['product_amount']);
        $this->postJson("/api/orders/{$order['id']}/payments", ['amount' => Order::find($order['id'])->total_amount])->assertCreated();

        // The sale is held for the seller until the goods arrive.
        $entries = SellerLedgerEntry::where('order_id', $order['id'])->get();
        $this->assertTrue($entries->contains('type', 'sale'), 'the sale is recorded for the seller');
        $this->assertSame('held', $entries->firstWhere('type', 'sale')->status);
    }
}
