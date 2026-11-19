<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\CommissionDistribution;
use App\Support\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The customer pays a platform fee on top of the invoice; the partner commission is still worked out on the invoice alone. */
class CustomerFeeTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        app(Settings::class)->set('platform.customer_fee_percentage', 5);
    }

    public function test_the_fee_is_a_percentage_of_the_invoice_and_must_be_paid_with_it(): void
    {
        $id = $this->order(false);
        $order = Order::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame(5.0, (float) $order->service_fee_rate);

        // Accepting a quote fixes the invoice, and the fee follows it.
        $quote = \App\Models\PartnerQuote::withoutGlobalScopes()->where('order_id', $id)->where('partner_id', $this->shop->id)->first();
        $this->actingAs($this->manager, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}", ['price' => 200])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->patchJson("/api/partner-quotes/{$quote->id}/accept")->assertOk();

        $order = Order::withoutGlobalScopes()->findOrFail($id);
        $invoice = (float) $order->total_amount;
        $fee = round($invoice * 0.05, 2);
        $this->assertSame($fee, (float) $order->service_fee_amount);
        $this->assertSame(round($invoice + $fee, 2), $order->payable_amount);

        // Paying only the invoice is refused; the amount must include the fee.
        $this->postJson("/api/orders/{$id}/payments", ['amount' => $invoice])->assertUnprocessable();
        $this->postJson("/api/orders/{$id}/payments", ['amount' => $invoice + $fee])->assertCreated();

        $order = Order::withoutGlobalScopes()->findOrFail($id);
        $this->assertSame('paid', $order->status->value);

        // The partner commission is on the invoice, not on the fee; the fee is the platform's own income.
        $commission = $order->commission;
        $this->assertSame(round($invoice * 0.10, 2), (float) $commission->amount);
        $this->assertEqualsWithDelta($fee, (float) CommissionDistribution::where('commission_id', $commission->id)->where('type', 'service_fee')->sum('amount'), 0.001);
        $this->assertEqualsWithDelta($invoice + $fee, (float) $order->payments()->sum('amount'), 0.001);
    }
}
