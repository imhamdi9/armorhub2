<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\Warranty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Warranty claims on goods, and the invoice a customer can download as a PDF. */
class WarrantyClaimsAndInvoiceTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private Order $order;
    private Warranty $warranty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $co = Company::create(['tenant_id' => $this->customer->company->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'gulf@parts.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
        $goods = Product::create(['name' => 'Brake Pads', 'slug' => 'bp-w', 'seller_id' => $co->id, 'base_price' => 100, 'is_active' => true, 'requires_shipping' => true, 'warranty_years' => 2]);
        $this->order = Order::withoutGlobalScopes()->forceCreate(['company_id' => $this->customer->company_id, 'customer_id' => $this->customer->id, 'product_id' => $goods->id, 'status' => 'completed',
            'quantity' => 2, 'product_amount' => 200, 'total_amount' => 200, 'service_fee_rate' => 5, 'service_fee_amount' => 10, 'shipping_amount' => 30, 'currency' => 'EGP', 'tax_rate' => 14, 'tax_amount' => 24.56]);
        Payment::withoutGlobalScopes()->forceCreate(['order_id' => $this->order->id, 'company_id' => $this->order->company_id, 'amount' => 240, 'currency' => 'EGP', 'status' => 'completed']);
        $this->warranty = Warranty::withoutGlobalScopes()->create(['order_id' => $this->order->id, 'company_id' => $this->order->company_id, 'customer_id' => $this->customer->id, 'product_id' => $goods->id,
            'starts_at' => now(), 'ends_at' => now()->addYears(2)]);
    }

    public function test_a_claim_is_decided_by_the_seller_and_followed_to_the_end(): void
    {
        $this->actingAs($this->customer, 'sanctum');
        $this->assertTrue($this->getJson('/api/warranties')->json('0.can_claim'));
        $this->postJson("/api/warranties/{$this->warranty->id}/claims", ['description' => 'Too short'])->assertUnprocessable();
        $id = $this->postJson("/api/warranties/{$this->warranty->id}/claims", ['description' => 'The pads squeal and wear on one side after a month.'])->assertCreated()->assertJsonPath('status', 'open')->json('id');
        $this->assertSame(1, $this->seller->notifications()->count());                                                  // the seller is told
        $this->postJson("/api/warranties/{$this->warranty->id}/claims", ['description' => 'Another problem with the same pads'])->assertUnprocessable();   // one at a time
        $this->assertFalse($this->getJson('/api/warranties')->json('0.can_claim'));

        // Only the selling company (or an administrator) decides; a rival and the customer cannot.
        $rival = User::factory()->create(['email' => 'rival@parts.example.org', 'company_id' => Company::create(['tenant_id' => $this->customer->company->tenant_id, 'name' => 'Rival', 'type' => 'supplier', 'status' => 'active'])->id]);
        $rival->assignRole('seller');
        $this->actingAs($rival, 'sanctum')->putJson("/api/warranty-claims/{$id}", ['status' => 'approved', 'resolution' => 'refund'])->assertNotFound();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/warranty-claims')->assertForbidden();

        $this->actingAs($this->seller, 'sanctum');
        $this->assertSame('Brake Pads', $this->getJson('/api/warranty-claims')->json('0.product.name'));
        $this->putJson("/api/warranty-claims/{$id}", ['status' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('resolution');
        $this->putJson("/api/warranty-claims/{$id}", ['status' => 'resolved'])->assertUnprocessable();                   // not before it is approved
        $this->putJson("/api/warranty-claims/{$id}", ['status' => 'approved', 'resolution' => 'replace', 'note' => 'New set on its way'])->assertOk()->assertJsonPath('resolution', 'replace');
        $this->putJson("/api/warranty-claims/{$id}", ['status' => 'rejected', 'note' => 'changed my mind'])->assertUnprocessable();                 // decided once
        $this->putJson("/api/warranty-claims/{$id}", ['status' => 'resolved'])->assertOk()->assertJsonPath('status', 'resolved');
        $this->assertTrue($this->customer->notifications()->count() >= 2);

        // Resolved: the customer may claim again.
        $this->actingAs($this->customer, 'sanctum');
        $this->assertTrue($this->getJson('/api/warranties')->json('0.can_claim'));
        $this->assertSame('resolved', $this->getJson('/api/warranties')->json('0.claims.0.status'));
    }

    public function test_a_declined_claim_needs_a_reason_and_an_ended_warranty_takes_no_claims(): void
    {
        $id = $this->actingAs($this->customer, 'sanctum')->postJson("/api/warranties/{$this->warranty->id}/claims", ['description' => 'It stopped working after a week.'])->json('id');
        $this->actingAs($this->seller, 'sanctum')->putJson("/api/warranty-claims/{$id}", ['status' => 'rejected'])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->putJson("/api/warranty-claims/{$id}", ['status' => 'rejected', 'note' => 'Damage from a crash is not covered'])->assertOk()->assertJsonPath('note', 'Damage from a crash is not covered');

        $this->warranty->update(['ends_at' => now()->subDay()]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/warranties/{$this->warranty->id}/claims", ['description' => 'Trying again after it ended'])->assertUnprocessable();
        $this->assertFalse($this->getJson('/api/warranties')->json('0.can_claim'));
    }

    public function test_the_invoice_is_a_pdf_only_the_customer_and_staff_can_download_and_only_once_paid(): void
    {
        $res = $this->actingAs($this->customer, 'sanctum')->get("/api/orders/{$this->order->id}/invoice.pdf")->assertOk();
        $res->assertHeader('Content-Type', 'application/pdf');
        $pdf = $res->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF\n", $pdf);
        $this->assertStringContainsString('INV-', $pdf);
        $this->assertStringContainsString('Brake Pads', $pdf);
        $this->assertStringContainsString('Total paid', $pdf);
        $this->assertStringContainsString('240.00 EGP', $pdf);                                                         // 200 + 10 fee + 30 delivery

        // The xref table points at real objects: every offset starts an "N 0 obj".
        preg_match('/startxref\n(\d+)/', $pdf, $m);
        $this->assertStringStartsWith('xref', substr($pdf, (int) $m[1], 4));
        preg_match_all('/(\d{10}) 00000 n/', $pdf, $offsets);
        foreach ($offsets[1] as $i => $offset) {
            $this->assertMatchesRegularExpression('/^'.($i + 1).' 0 obj/', substr($pdf, (int) $offset, 12));
        }

        $stranger = User::factory()->create(['company_id' => $this->customer->company_id]);
        $stranger->assignRole('customer');
        $this->actingAs($stranger, 'sanctum')->get("/api/orders/{$this->order->id}/invoice.pdf")->assertForbidden();

        Payment::withoutGlobalScopes()->where('order_id', $this->order->id)->update(['status' => 'pending']);
        $this->actingAs($this->customer, 'sanctum')->get("/api/orders/{$this->order->id}/invoice.pdf")->assertNotFound();      // nothing paid, nothing invoiced
    }

    public function test_arabic_names_never_break_the_pdf(): void
    {
        $this->order->product->update(['name' => 'تيل فرامل سيراميك']);
        $pdf = $this->actingAs($this->customer, 'sanctum')->get("/api/orders/{$this->order->id}/invoice.pdf")->assertOk()->getContent();
        $this->assertStringContainsString('Product ', $pdf);                                                            // the Latin text can't carry it, so the invoice says which product by code
        $this->assertSame(1, preg_match('/^[\x00-\x7F]*$/', $pdf));                                                     // and the file stays plain, valid PDF
    }
}
