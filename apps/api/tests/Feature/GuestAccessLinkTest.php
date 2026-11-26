<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** A guest shopper who opens our e-mail on another device, or after their session ran out, gets back to their order. */
class GuestAccessLinkTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private User $guest;
    private int $orderId;
    private string $link;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
        config(['platform.web_url' => 'https://shop.example.org']);
        $store = Company::withoutGlobalScopes()->where('name', 'Store')->first();
        $store->update(['slug' => 'main-store', 'settings' => ['storefront' => ['enabled' => true]]]);
        $seller = tap(User::factory()->create(['company_id' => Company::create(['tenant_id' => $store->tenant_id, 'name' => 'Nile Goods', 'type' => 'supplier', 'status' => 'active'])->id]), fn ($u) => $u->assignRole('seller'));
        $cat = $this->actingAs($seller, 'sanctum')->getJson('/api/products/categories')->json('0.key');
        $product = $this->postJson('/api/products', ['name' => 'Olive Soap', 'base_price' => 100, 'category' => $cat])->assertCreated()->json('id');
        $this->app['auth']->forgetGuards();

        Notification::fake();
        $token = $this->postJson('/api/guest-session', ['store' => 'main-store'])->assertCreated()->json('token');
        $this->withHeader('Authorization', 'Bearer '.$token);
        $this->postJson('/api/cart/items', ['product_id' => $product])->assertCreated();
        $this->putJson('/api/guest-session/contact', ['email' => 'layla@shoppers.example.org', 'name' => 'Layla'])->assertOk();
        $group = $this->postJson('/api/checkout', ['shipping_address' => ['name' => 'Layla', 'phone' => '0501234567', 'line1' => '12 King Road', 'city' => 'Riyadh']])->assertCreated()->json('id');
        $this->postJson("/api/order-groups/{$group}/payments", ['payment_method' => 'card'])->assertOk();

        $this->orderId = (int) Order::withoutGlobalScopes()->where('group_id', $group)->value('id');
        $this->guest = User::where('is_guest', true)->firstOrFail();
        $mail = Notification::sent($this->guest, OrderUpdate::class)->first(fn ($n) => $n->orderId === $this->orderId);
        $this->link = $mail->toMail($this->guest)->actionUrl;
        $this->newDevice();
    }

    /** Nothing carried over: no session, no token. */
    private function newDevice(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }

    /** @return array<string, string> */
    private function parts(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_FRAGMENT), $parts);

        return $parts;
    }

    public function test_the_order_email_lets_the_guest_in_on_a_new_device(): void
    {
        $this->assertStringStartsWith('https://shop.example.org/guest-access#', $this->link);   // the secret stays after the #, out of server logs
        $this->assertSame("/dashboard/orders/{$this->orderId}", $this->parts($this->link)['to']);
        $this->getJson("/api/orders/{$this->orderId}")->assertUnauthorized();

        $token = $this->postJson('/api/guest-session/open', ['token' => $this->parts($this->link)['t']])->assertOk()->assertJsonPath('user.id', $this->guest->id)->json('token');
        $this->newDevice();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson("/api/orders/{$this->orderId}")->assertOk();
    }

    public function test_a_changed_or_made_up_link_opens_nothing(): void
    {
        $t = $this->parts($this->link)['t'];
        [$id, $expires, $signature] = explode('.', $t);

        foreach (['', 'nonsense', "{$this->customer->id}.{$expires}.{$signature}", "{$id}.".($expires + 1000).".{$signature}", "{$id}.{$expires}.".str_repeat('0', 64)] as $bad) {
            $this->postJson('/api/guest-session/open', ['token' => $bad])->assertUnprocessable()->assertJsonValidationErrors(['token']);
        }
    }

    public function test_the_link_ends_with_time_a_new_email_address_or_a_claimed_account(): void
    {
        $t = $this->parts($this->link)['t'];

        $this->travel(31)->days();
        $this->postJson('/api/guest-session/open', ['token' => $t])->assertUnprocessable();
        $this->travelBack();

        $this->guest->forceFill(['contact_email' => 'other@shoppers.example.org'])->save();
        $this->postJson('/api/guest-session/open', ['token' => $t])->assertUnprocessable();
        $this->guest->forceFill(['contact_email' => 'layla@shoppers.example.org'])->save();
        $this->postJson('/api/guest-session/open', ['token' => $t])->assertOk();

        $this->newDevice();
        $this->guest->forceFill(['is_guest' => false])->save();
        $this->postJson('/api/guest-session/open', ['token' => $t])->assertUnprocessable();
    }

    public function test_a_signed_in_customer_is_not_switched_to_the_guest(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/guest-session/open', ['token' => $this->parts($this->link)['t']])
            ->assertUnprocessable()->assertJsonPath('errors.token.0', 'You are signed in with another account. Sign out, then open the link again.');
    }

    public function test_a_customer_with_an_account_gets_the_plain_link(): void
    {
        $mail = (new OrderUpdate(1, 'shipped', 'Shipped', 'On its way', '/dashboard/orders/1'))->toMail($this->customer);
        $this->assertSame('https://shop.example.org/dashboard/orders/1', $mail->actionUrl);
    }
}
