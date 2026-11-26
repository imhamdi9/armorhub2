<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\SimpleNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/** What the phone apps need from the platform: a stable address, push notifications and deleting the account. */
class MobileApiTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    private const TOKEN = 'ExponentPushToken[abc123_DEF-456]';

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    public function test_the_versioned_address_works_like_the_plain_one(): void
    {
        $this->getJson('/api/v1/public/markets')->assertOk();
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/v1/me')->assertOk()->assertJsonPath('id', $this->customer->id);
        // And it is just as closed to people who are not signed in.
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_a_phone_registers_once_and_follows_the_person_signed_in_on_it(): void
    {
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/me/devices', ['platform' => 'ios', 'token' => self::TOKEN])->assertCreated();
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/me/devices', ['platform' => 'ios', 'token' => self::TOKEN])->assertCreated();
        $this->assertSame(1, DeviceToken::count());

        // Another person signs in on the same phone: the alerts go to them now, not to the previous owner.
        $this->actingAs($this->manager, 'sanctum')->postJson('/api/me/devices', ['platform' => 'ios', 'token' => self::TOKEN])->assertCreated();
        $this->assertSame($this->manager->id, DeviceToken::first()->user_id);

        // Only tokens of the right shape are accepted.
        foreach (['not-a-token', 'ExponentPushToken[]', 'ExponentPushToken[a b]', str_repeat('x', 300)] as $bad) {
            $this->actingAs($this->customer, 'sanctum')->postJson('/api/me/devices', ['platform' => 'android', 'token' => $bad])->assertUnprocessable();
        }
        $this->actingAs($this->customer, 'sanctum')->postJson('/api/me/devices', ['platform' => 'web', 'token' => self::TOKEN])->assertUnprocessable();

        // Signing out of the app removes only your own phone's token.
        $this->actingAs($this->customer, 'sanctum')->deleteJson('/api/me/devices', ['token' => self::TOKEN])->assertOk();
        $this->assertSame(1, DeviceToken::count());
        $this->actingAs($this->manager, 'sanctum')->deleteJson('/api/me/devices', ['token' => self::TOKEN])->assertOk();
        $this->assertSame(0, DeviceToken::count());
    }

    public function test_an_inbox_notification_is_also_pushed_and_a_dead_phone_is_forgotten(): void
    {
        config(['services.expo_push.enabled' => true]);
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok'], ['status' => 'error', 'details' => ['error' => 'DeviceNotRegistered']]]])]);
        DeviceToken::create(['user_id' => $this->customer->id, 'platform' => 'ios', 'token' => self::TOKEN]);
        DeviceToken::create(['user_id' => $this->customer->id, 'platform' => 'android', 'token' => 'ExponentPushToken[dead]']);

        $this->customer->notify(new SimpleNotice('Your order moved', 'It is on its way.', '/dashboard/orders/1'));

        Http::assertSent(fn ($r) => $r->url() === 'https://exp.host/--/api/v2/push/send' && $r[0]['title'] === 'Your order moved' && $r[0]['data']['path'] === '/dashboard/orders/1' && count($r->data()) === 2);
        $this->assertSame([self::TOKEN], DeviceToken::pluck('token')->all());
    }

    public function test_nothing_is_sent_when_push_is_off_and_a_failure_never_breaks_the_notification(): void
    {
        Http::fake();
        DeviceToken::create(['user_id' => $this->customer->id, 'platform' => 'ios', 'token' => self::TOKEN]);
        $this->customer->notify(new SimpleNotice('Hello', 'x', '/dashboard'));
        Http::assertNothingSent();

        config(['services.expo_push.enabled' => true]);
        Http::fake(['exp.host/*' => Http::response('down', 500)]);
        $this->customer->notify(new SimpleNotice('Again', 'x', '/dashboard'));
        $this->assertSame(2, $this->customer->notifications()->count());
    }

    public function test_a_customer_can_delete_their_account_and_loses_everything_about_them(): void
    {
        $this->customer->update(['phone' => '+966500000009']);
        DeviceToken::create(['user_id' => $this->customer->id, 'platform' => 'ios', 'token' => self::TOKEN]);
        $this->customer->notify(new SimpleNotice('Hello', 'x', '/dashboard'));

        $this->actingAs($this->customer, 'sanctum')->deleteJson('/api/me', ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->actingAs($this->customer, 'sanctum')->deleteJson('/api/me', ['password' => 'password'])->assertOk();

        $gone = User::find($this->customer->id);
        $this->assertFalse((bool) $gone->is_active);
        $this->assertNull($gone->phone);
        $this->assertSame('Deleted user', $gone->name);
        $this->assertStringEndsWith('@deleted.invalid', $gone->email);
        $this->assertSame(0, DeviceToken::count());
        $this->assertSame(0, $gone->notifications()->count());
    }

    public function test_someone_with_google_confirms_by_typing_delete(): void
    {
        SocialAccount::create(['user_id' => $this->customer->id, 'provider' => 'google', 'provider_user_id' => 'g-1']);

        $this->actingAs($this->customer, 'sanctum')->deleteJson('/api/me', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->actingAs($this->customer, 'sanctum')->deleteJson('/api/me', ['confirm' => 'DELETE'])->assertOk();
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_businesses_and_people_with_an_order_in_progress_cannot_delete_from_the_app(): void
    {
        $this->actingAs($this->manager, 'sanctum')->deleteJson('/api/me', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->actingAs($this->admin, 'sanctum')->deleteJson('/api/me', ['password' => 'password'])->assertUnprocessable();

        $this->order(false);
        $this->assertTrue(Order::withoutGlobalScopes()->where('customer_id', $this->customer->id)->exists());
        $this->actingAs($this->customer, 'sanctum')->deleteJson('/api/me', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('account');
        $this->assertTrue((bool) User::find($this->customer->id)->is_active);
    }

    public function test_the_app_signs_in_with_a_token_it_keeps_itself_and_the_same_account_works_on_the_website(): void
    {
        $res = $this->withHeaders(['X-Client' => 'mobile'])->postJson('/api/v1/login', ['email' => $this->customer->email, 'password' => 'password'])->assertOk();
        $token = $res->json('token');
        $this->assertNotEmpty($token);
        $this->assertNull($res->headers->getCookies() ? $res->getCookie('armorhub_token', false) : null);

        // The token opens the same account the website uses.
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('id', $this->customer->id);

        // The website still gets a cookie and no token in the body.
        $web = $this->withHeaders(['X-Client' => 'web'])->postJson('/api/login', ['email' => $this->customer->email, 'password' => 'password'])->assertOk();
        $this->assertNull($web->json('token'));
    }
}
