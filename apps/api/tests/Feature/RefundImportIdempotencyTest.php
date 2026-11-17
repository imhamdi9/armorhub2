<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\SellerLedgerEntry;
use App\Models\User;
use App\Services\Ledger\Accounts;
use App\Services\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** Money back for a product order, paying only once on a retry, and adding products from a spreadsheet. */
class RefundImportIdempotencyTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $seller;
    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $this->seller = User::factory()->create(['email' => 'a@sellers.example.org', 'company_id' => $co->id]);
        $this->seller->assignRole('seller');
    }

    /** A product of the seller's, priced for the hood, in the customer's cart; returns the checkout group. */
    private function checkedOut(string $name, int $price): array
    {
        $id = $this->actingAs($this->seller, 'sanctum')->postJson('/api/products', ['name' => $name, 'slug' => str($name)->slug().'-x', 'base_price' => $price, 'category' => 'spare-parts'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => $price, 'is_active' => true]]])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertCreated();

        return $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
    }

    /** @return array{0: array, 1: Order} */
    private function paidOrder(): array
    {
        $group = $this->checkedOut('Brake pads', 100);
        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'])->assertOk();

        return [$group, Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail()];
    }

    public function test_a_paid_order_is_refunded_in_full_and_the_books_balance(): void
    {
        [$group, $order] = $this->paidOrder();
        $ledger = app(Ledger::class);
        $sellerId = $this->seller->company_id;
        $this->assertGreaterThan(0, $ledger->balance(Accounts::sellerHeld($sellerId), $order->currency));

        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'Damaged on arrival'])
            ->assertOk()->assertJsonPath('refund_status', 'pending');
        $this->assertEqualsWithDelta(0, $ledger->balance(Accounts::sellerHeld($sellerId), $order->currency), 0.001);
        $this->assertEqualsWithDelta($group['payable_amount'], $ledger->balance(Accounts::REFUND_DUE, $order->currency), 0.001);
        $this->assertSame(0, SellerLedgerEntry::where('order_id', $order->id)->where('status', 'held')->count());

        $this->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'again'])->assertUnprocessable();
        $this->postJson("/api/admin/orders/{$order->id}/refund-sent", ['reference' => 'REF-1'])->assertOk()->assertJsonPath('refund_status', 'refunded');
        $this->assertEqualsWithDelta(0, $ledger->balance(Accounts::GATEWAY, $order->currency), 0.001);

        $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$order->id}")->assertOk()->assertJsonPath('statuses.payment', 'refunded');
    }

    public function test_only_administrators_refund_and_only_orders_that_were_paid(): void
    {
        [, $order] = $this->paidOrder();
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'x y z'])->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", [])->assertUnprocessable();
        $this->postJson("/api/admin/orders/{$order->id}/refund-sent", ['reference' => 'R'])->assertUnprocessable();
    }

    public function test_an_order_the_seller_was_already_paid_for_is_not_refunded_here(): void
    {
        [, $order] = $this->paidOrder();
        SellerLedgerEntry::where('order_id', $order->id)->update(['status' => 'paid']);
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/orders/{$order->id}/refund", ['reason' => 'late request'])->assertUnprocessable();
        SellerLedgerEntry::where('order_id', $order->id)->update(['status' => 'held']);
    }

    public function test_the_same_idempotency_key_pays_once(): void
    {
        $group = $this->checkedOut('Wipers', 40);
        $h = ['Idempotency-Key' => 'pay-123'];

        $first = $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'], $h)->assertOk();
        $again = $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'card'], $h)->assertOk();
        $again->assertHeader('Idempotent-Replayed', 'true');
        $this->assertSame($first->json(), $again->json());
        $ids = Order::withoutGlobalScopes()->where('group_id', $group['id'])->pluck('id');
        $this->assertSame(1, \App\Models\Payment::withoutGlobalScopes()->whereIn('order_id', $ids)->count());

        $this->postJson("/api/order-groups/{$group['id']}/payments", ['payment_method' => 'wallet'], $h)->assertUnprocessable();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [], ['Idempotency-Key' => 'bad key!'])->assertUnprocessable();
    }

    private function csv(string $body): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('products.csv', $body);
    }

    public function test_a_spreadsheet_adds_products_all_or_nothing(): void
    {
        $this->actingAs($this->seller, 'sanctum');
        $good = "name,category,base_price,sku,brand\nBrake pads,spare-parts,120,BP-100,Acme\nOil filter,spare-parts,35.5,OF-200,Acme\n";

        $this->postJson('/api/products/import', ['file' => $this->csv($good), 'dry_run' => true])->assertOk()->assertJsonPath('imported', 0)->assertJsonPath('rows', 2);
        $this->assertSame(0, \App\Models\Product::withoutGlobalScopes()->where('sku', 'BP-100')->count());

        $this->postJson('/api/products/import', ['file' => $this->csv($good)])->assertCreated()->assertJsonPath('imported', 2);
        $p = \App\Models\Product::withoutGlobalScopes()->where('sku', 'BP-100')->firstOrFail();
        $this->assertSame($this->seller->company_id, (int) $p->seller_id);

        $bad = "name,category,base_price,sku\nGood one,spare-parts,10,NEW-1\nNo price,spare-parts,abc,NEW-2\nDuplicate,spare-parts,5,BP-100\n";
        $r = $this->postJson('/api/products/import', ['file' => $this->csv($bad)])->assertUnprocessable()->json();
        $this->assertSame([3, 4], array_column($r['errors'], 'row'));
        $this->assertSame(0, \App\Models\Product::withoutGlobalScopes()->where('sku', 'NEW-1')->count());

        $this->postJson('/api/products/import', ['file' => $this->csv("title,price\nx,1\n")])->assertUnprocessable();
    }

    public function test_an_excel_file_is_read_too(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><si><t>name</t></si><si><t>category</t></si><si><t>base_price</t></si><si><t>Air filter</t></si><si><t>spare-parts</t></si></sst>');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'
            .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c></row>'
            .'<row r="2"><c r="A2" t="s"><v>3</v></c><c r="B2" t="s"><v>4</v></c><c r="C2"><v>64.5</v></c></row></sheetData></worksheet>');
        $zip->close();

        $this->actingAs($this->seller, 'sanctum')->postJson('/api/products/import', ['file' => new UploadedFile($path, 'products.xlsx', null, null, true)])->assertCreated()->assertJsonPath('imported', 1);
        $this->assertEquals(64.5, \App\Models\Product::withoutGlobalScopes()->where('name', 'Air filter')->value('base_price'));
    }

    public function test_a_customer_cancelling_before_shipping_is_refunded_at_once(): void
    {
        [$group, $order] = $this->paidOrder();
        $ledger = app(Ledger::class);
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$order->id}/refund-request")->assertOk()->assertJsonPath('can_request', true);

        $this->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Changed my mind'])->assertCreated()->assertJsonPath('kind', 'cancel')->assertJsonPath('status', 'approved');
        $this->assertSame('pending', Order::withoutGlobalScopes()->find($order->id)->refund_status);
        $this->assertEqualsWithDelta($group['payable_amount'], $ledger->balance(Accounts::REFUND_DUE, $order->currency), 0.001);
        $this->assertEqualsWithDelta(0, $ledger->balance(Accounts::sellerHeld($this->seller->company_id), $order->currency), 0.001);
        $this->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'again please'])->assertUnprocessable();
        $this->getJson("/api/orders/{$order->id}/refund-request")->assertJsonPath('can_request', false)->assertJsonPath('refund_status', 'pending');
    }

    public function test_after_shipping_the_request_waits_for_an_administrator(): void
    {
        [, $order] = $this->paidOrder();
        $shipment = \App\Models\Shipment::create(['order_id' => $order->id, 'provider' => 'manual', 'status' => \App\Models\Shipment::IN_TRANSIT]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'It is on its way but I do not want it'])
            ->assertCreated()->assertJsonPath('kind', 'return')->assertJsonPath('status', 'requested');
        $this->assertNull(Order::withoutGlobalScopes()->find($order->id)->refund_status);                        // nothing moved yet
        $this->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'second request'])->assertUnprocessable();

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/refund-requests')->assertForbidden();
        $this->actingAs($this->admin, 'sanctum');
        $id = $this->getJson('/api/admin/refund-requests')->assertOk()->assertJsonCount(1)->json('0.id');
        $this->postJson("/api/admin/refund-requests/{$id}/reject", [])->assertUnprocessable();
        $this->postJson("/api/admin/refund-requests/{$id}/approve")->assertOk()->assertJsonPath('status', 'approved');
        $this->assertSame('pending', Order::withoutGlobalScopes()->find($order->id)->refund_status);
        $this->postJson("/api/admin/refund-requests/{$id}/approve")->assertUnprocessable();                       // decided once
        $this->assertNotNull($shipment->fresh());
    }

    public function test_a_rejected_request_leaves_the_order_as_it_was_and_only_the_owner_can_ask(): void
    {
        [, $order] = $this->paidOrder();
        \App\Models\Shipment::create(['order_id' => $order->id, 'provider' => 'manual', 'status' => \App\Models\Shipment::DELIVERED, 'delivered_at' => now()->subDays(2)]);

        $other = User::factory()->create(['company_id' => $this->customer->company_id]);
        $other->assignRole('customer');
        $this->actingAs($other, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'not mine to ask'])->assertNotFound();

        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'Does not work'])->assertCreated()->assertJsonPath('kind', 'return');
        $id = \App\Models\OrderRefundRequest::firstOrFail()->id;
        $this->actingAs($this->admin, 'sanctum')->postJson("/api/admin/refund-requests/{$id}/reject", ['note' => 'Used and damaged'])->assertOk()->assertJsonPath('status', 'rejected');
        $this->assertNull(Order::withoutGlobalScopes()->find($order->id)->refund_status);
        $this->actingAs($this->customer, 'sanctum')->getJson("/api/orders/{$order->id}/refund-request")->assertJsonPath('request.status', 'rejected')->assertJsonPath('request.note', 'Used and damaged');
    }

    public function test_the_return_window_closes(): void
    {
        [, $order] = $this->paidOrder();
        \App\Models\Shipment::create(['order_id' => $order->id, 'provider' => 'manual', 'status' => \App\Models\Shipment::DELIVERED, 'delivered_at' => now()->subDays(40)]);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$order->id}/refund-request", ['reason' => 'too late now'])->assertUnprocessable();
    }

    public function test_a_customer_cannot_import(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/products/import', ['file' => $this->csv("name,category,base_price\na,spare-parts,1\n")])->assertForbidden();
    }
}
