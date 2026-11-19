<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderEvent;
use App\Notifications\OrderUpdate;
use App\Services\OrderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

class NotificationsTest extends TestCase
{
    use BuildsMarketplace;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildMarketplace();
    }

    private function titles($user): array
    {
        return $user->notifications()->orderBy('created_at')->get()->pluck('data.title')->all();
    }

    public function test_each_step_notifies_the_right_people(): void
    {
        $id = $this->order(); // placed, quoted, accepted, paid

        // The partner manager hears about the request and the acceptance; the paid job reaches the technician too.
        $this->assertEqualsCanonicalizing(['New quote request', 'Your quote was accepted', 'New paid job'], $this->titles($this->manager));
        $this->assertSame(['New paid job'], $this->titles($this->tech));

        // The customer hears about the quote and the payment, not about their own actions.
        $this->assertEqualsCanonicalizing(['You received a quote', 'Payment received'], $this->titles($this->customer));

        // A rival who was never asked hears nothing.
        $this->assertSame([], $this->titles($this->rival));

        $when = now()->addDay()->toIso8601String();
        $this->actingAs($this->tech, 'sanctum')->postJson("/api/orders/{$id}/schedule", ['scheduled_at' => $when])->assertOk();
        $this->postJson("/api/orders/{$id}/start")->assertOk();
        $this->postJson("/api/orders/{$id}/complete")->assertOk();

        $this->assertEqualsCanonicalizing(
            ['You received a quote', 'Payment received', 'Appointment scheduled', 'Work has started', 'Work completed'],
            $this->titles($this->customer)
        );
    }

    public function test_cancelling_tells_the_asked_partners_but_not_the_customer(): void
    {
        $id = $this->order(false);
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/orders/{$id}/cancel")->assertOk();

        $this->assertContains('Order cancelled', $this->titles($this->manager));
        $this->assertNotContains('Order cancelled', $this->titles($this->customer));
    }

    public function test_deactivated_users_are_not_notified(): void
    {
        $this->manager->update(['is_active' => false]);
        $this->order(false);

        $this->assertSame([], $this->titles($this->manager));
    }

    public function test_an_email_is_sent_with_a_link_into_the_app(): void
    {
        Notification::fake();
        $this->order(false);

        Notification::assertSentTo($this->manager, OrderUpdate::class, function (OrderUpdate $n) {
            $mail = $n->toMail($this->manager);
            $this->assertSame('New quote request', $mail->subject);
            $this->assertSame('http://localhost:3000/dashboard/requests', $mail->actionUrl);
            $this->assertSame(['database', 'mail'], $n->via($this->manager));

            return true;
        });
    }

    public function test_the_real_notifier_swallows_mail_errors(): void
    {
        // Make delivery blow up for everyone.
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Notifications\Events\NotificationSending::class,
            fn () => throw new \RuntimeException('smtp down')
        );

        $id = $this->order(false); // would 500 if the error escaped

        $this->assertNotNull(Order::withoutGlobalScopes()->find($id));
        $this->assertSame(1, OrderEvent::where('type', 'created')->count());
    }

    public function test_inbox_api(): void
    {
        $this->order(false);
        $this->actingAs($this->manager, 'sanctum');

        $inbox = $this->getJson('/api/notifications')->assertOk();
        $inbox->assertJsonPath('unread', 1)->assertJsonPath('items.0.title', 'New quote request')
            ->assertJsonPath('items.0.path', '/dashboard/requests')->assertJsonPath('items.0.read', false);

        $this->getJson('/api/notifications/unread-count')->assertJsonPath('unread', 1);

        $id = $inbox->json('items.0.id');
        $this->postJson("/api/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/notifications/unread-count')->assertJsonPath('unread', 0);

        // Nobody can read someone else's notification.
        $this->actingAs($this->customer, 'sanctum')->postJson("/api/notifications/{$id}/read")->assertNotFound();

        $this->actingAs($this->manager, 'sanctum')->postJson('/api/notifications/read-all')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread', 0);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/notifications')->assertUnauthorized();
    }

    public function test_admins_can_reword_a_notification_and_nothing_else_changes(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->putJson('/api/admin/surfaces/app.messages', ['locale' => 'en', 'values' => ['templates' => [
            ['kind' => 'payment_received', 'title' => 'Thanks for paying order #{order_id}', 'body' => 'We got your payment for {name}. {unknown}'],
        ]]])->assertOk();
        $this->postJson('/api/admin/surfaces/app.messages/publish', ['locale' => 'en'])->assertOk();

        $this->order();

        $payment = $this->customer->notifications()->where('data->kind', 'payment_received')->first()->data;
        $this->assertStringStartsWith('Thanks for paying order #', $payment['title']);
        $this->assertStringContainsString('{unknown}', $payment['body']);           // unknown placeholders are left alone
        $this->assertStringNotContainsString('{name}', $payment['body']);

        // A message nobody reworded keeps the built-in wording.
        $this->assertContains('You received a quote', $this->titles($this->customer));
    }

    public function test_a_draft_or_invalid_wording_never_changes_messages(): void
    {
        $this->actingAs($this->admin, 'sanctum');
        $this->putJson('/api/admin/surfaces/app.messages', ['locale' => 'en', 'values' => ['templates' => [['kind' => 'payment_received', 'title' => 'DRAFT']]]])->assertOk();
        $this->putJson('/api/admin/surfaces/app.messages', ['locale' => 'en', 'values' => ['templates' => [['kind' => 'not_a_message', 'title' => 'x']]]])->assertStatus(422);

        $this->order();

        $this->assertContains('Payment received', $this->titles($this->customer));
    }
}
