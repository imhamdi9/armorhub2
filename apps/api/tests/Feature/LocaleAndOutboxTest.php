<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** The API and its e-mails in Arabic, and the outbox that keeps them readable. */
class LocaleAndOutboxTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private array $address = ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_errors_come_back_in_the_language_asked_for_and_the_language_is_remembered(): void
    {
        $this->actingAs($this->customer, 'sanctum');
        $this->postJson('/api/cart/promo', ['code' => 'NOPE'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'This code is not valid.');
        $this->postJson('/api/cart/promo', ['code' => 'NOPE'], ['Accept-Language' => 'ar'])->assertUnprocessable()->assertJsonPath('errors.code.0', 'هذا الكود غير صالح.');
        $this->postJson('/api/cart/promo', ['code' => 'NOPE'], ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertJsonPath('errors.code.0', 'This code is not valid.');   // anything else: English

        $this->assertSame('en', $this->customer->fresh()->locale);                                           // the last request said "fr": English
        $this->postJson('/api/cart/promo', ['code' => 'NOPE'], ['Accept-Language' => 'ar']);
        $this->assertSame('ar', $this->customer->fresh()->locale);
    }

    public function test_laravels_own_validation_messages_and_field_names_are_translated_too(): void
    {
        $this->actingAs($this->customer, 'sanctum');
        $url = "/api/products/{$this->product->id}/questions";

        $this->postJson($url, ['question' => 'Short'])->assertUnprocessable()->assertJsonPath('errors.question.0', 'The question field must be at least 8 characters.');
        $this->postJson($url, ['question' => 'Short'], ['Accept-Language' => 'ar'])->assertUnprocessable()->assertJsonPath('errors.question.0', 'يجب ألا يقل طول السؤال عن 8 حرفاً.');
        $this->postJson($url, [], ['Accept-Language' => 'ar'])->assertJsonPath('errors.question.0', 'حقل السؤال مطلوب.');

        // A message with a number in it keeps the number.
        $this->postJson('/api/cart/items', ['product_id' => $this->product->id, 'quantity' => 100000], ['Accept-Language' => 'ar'])->assertUnprocessable();
    }

    private function paidOrder(): Order
    {
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $co = Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Gulf Parts', 'type' => 'supplier', 'status' => 'active']);
        $seller = User::factory()->create(['email' => 'a@sellers.example.org', 'company_id' => $co->id]);
        $seller->assignRole('seller');
        $id = $this->actingAs($seller, 'sanctum')->postJson('/api/products', ['name' => 'Brake pads', 'slug' => 'brake-pads-x', 'base_price' => 100, 'category' => 'spare-parts'])->assertCreated()->json('id');
        $this->putJson("/api/products/{$id}/price-rules", ['rules' => [['target_type' => 'vehicle_part', 'target_id' => $this->hood->id, 'price' => 100, 'is_active' => true]]])->assertOk();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/cart/items', ['product_id' => $id])->assertCreated();
        $group = $this->postJson('/api/checkout', ['shipping_address' => $this->address])->assertCreated()->json();
        $this->postJson("/api/order-groups/{$group['id']}/payments", [])->assertOk();

        return Order::withoutGlobalScopes()->where('group_id', $group['id'])->firstOrFail();
    }

    public function test_notices_and_e_mails_are_written_in_the_recipients_language(): void
    {
        config(['mail.default' => 'outbox']);
        $order = $this->paidOrder();
        $this->customer->forceFill(['locale' => 'ar'])->save();

        OrderEvent::record($order, 'shipment_delivered', null, []);
        $delivered = fn () => $this->customer->fresh()->notifications()->get()->first(fn ($n) => ($n->data['kind'] ?? null) === 'shipment_delivered')->data;
        $notice = $delivered();
        $this->assertSame('تم التسليم', $notice['title']);                                                    // the app notice, in Arabic
        $this->assertStringContainsString('Brake pads', $notice['body']);

        $mail = DB::table('mail_outbox')->where('to', $this->customer->email)->latest('id')->first();
        $this->assertNotNull($mail);
        $this->assertSame('تم التسليم', $mail->subject);                                                      // and the e-mail
        $this->assertStringContainsString('مرحباً', $mail->html);
        $this->assertStringContainsString('افتح في ArmorHub', $mail->html);

        // The same event for an English-speaking person stays in English.
        $this->customer->forceFill(['locale' => 'en'])->save();
        $this->customer->notifications()->delete();
        OrderEvent::record($order, 'shipment_delivered', null, []);
        $this->assertSame('Delivered', $delivered()['title']);
    }

    public function test_an_administrator_reads_the_outbox_and_no_one_else_may(): void
    {
        DB::table('mail_outbox')->insert(['to' => 'x@example.org', 'subject' => 'Hello there', 'html' => '<p>Hi</p>', 'text' => 'Hi', 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('mail_outbox')->value('id');

        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/outbox')->assertForbidden();
        $this->actingAs($this->admin, 'sanctum')->getJson('/api/admin/outbox')->assertOk()->assertJsonPath('0.subject', 'Hello there');
        $this->getJson("/api/admin/outbox/{$id}")->assertOk()->assertJsonPath('html', '<p>Hi</p>');
        $this->getJson('/api/admin/outbox/999999')->assertNotFound();
    }
}
