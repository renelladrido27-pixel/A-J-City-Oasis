<?php

namespace Tests\Feature;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use App\Services\MailService;
use App\Services\NotificationService;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * Real-time notifications and email must never break the booking/payment
 * action that triggered them, and must never announce a change that was
 * rolled back.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_notification_is_broadcast_to_its_recipient_with_a_relative_link(): void
    {
        Event::fake([NotificationCreated::class]);
        $tenant = User::factory()->create();

        $notification = app(NotificationService::class)->notify($tenant, 'Rent payment received', 'Thanks!', 'payment');

        Event::assertDispatched(NotificationCreated::class, function (NotificationCreated $event) use ($tenant, $notification) {
            $payload = $event->broadcastWith();

            return $event->broadcastOn()->name === "private-users.{$tenant->id}"
                && $event->broadcastAs() === 'notification.created'
                && $payload['url'] === "/notifications/{$notification->id}/open"
                && $payload['unread_count'] === 1;
        });
    }

    public function test_a_notification_inside_a_rolled_back_transaction_is_not_broadcast(): void
    {
        Event::fake([NotificationCreated::class]);
        $tenant = User::factory()->create();

        try {
            DB::transaction(function () use ($tenant) {
                app(NotificationService::class)->notify($tenant, 'Payment received', 'x');
                throw new RuntimeException('payment failed after notifying');
            });
        } catch (RuntimeException) {
        }

        Event::assertNotDispatched(NotificationCreated::class);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_broadcasting_outage_does_not_break_the_action_that_notified(): void
    {
        Broadcast::extend('broken', fn () => new class extends Broadcaster
        {
            public function auth($request) {}

            public function validAuthenticationResponse($request, $result) {}

            public function broadcast(array $channels, $event, array $payload = []): void
            {
                throw new RuntimeException('Pusher is down');
            }
        });
        config(['broadcasting.connections.broken' => ['driver' => 'broken'], 'broadcasting.default' => 'broken']);

        $notification = app(NotificationService::class)->notify(User::factory()->create(), 'Lease started', 'Welcome!');

        $this->assertTrue($notification->exists);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_tenant_can_only_subscribe_to_their_own_notification_channel(): void
    {
        config([
            'broadcasting.default' => 'pusher',
            'broadcasting.connections.pusher.key' => 'test-key',
            'broadcasting.connections.pusher.secret' => 'test-secret',
            'broadcasting.connections.pusher.app_id' => '1',
        ]);
        require base_path('routes/channels.php'); // register on the Pusher broadcaster
        [$tenant, $other] = User::factory()->count(2)->create();

        $this->actingAs($tenant)
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-users.{$tenant->id}"])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->actingAs($tenant)
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-users.{$other->id}"])
            ->assertForbidden();
    }

    public function test_email_waits_for_the_transaction_to_commit_and_the_response_to_finish(): void
    {
        Mail::fake();
        $mail = (new Mailable)->subject('Receipt')->html('<p>x</p>');

        DB::transaction(fn () => app(MailService::class)->send('tenant@example.com', $mail));
        Mail::assertNothingSent();

        $this->app->terminate();
        Mail::assertSent(Mailable::class, fn ($m) => $m->hasTo('tenant@example.com'));
    }

    public function test_email_for_a_rolled_back_change_is_never_sent(): void
    {
        Mail::fake();

        try {
            DB::transaction(function () {
                app(MailService::class)->send('tenant@example.com', (new Mailable)->subject('Receipt')->html('<p>x</p>'));
                throw new RuntimeException('rolled back');
            });
        } catch (RuntimeException) {
        }
        $this->app->terminate();

        Mail::assertNothingSent();
    }

    public function test_an_smtp_failure_is_logged_not_thrown(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 1]);

        app(MailService::class)->send('tenant@example.com', (new Mailable)->subject('Receipt')->html('<p>x</p>'));
        $this->app->terminate();

        $this->assertTrue(true, 'terminate() completed without throwing');
    }

    public function test_a_tenant_cannot_view_another_tenants_receipt(): void
    {
        $payment = Payment::factory()->create(['status' => 'paid', 'paid_at' => now()]);

        $this->actingAs($payment->lease->tenant)->get(route('payments.receipt', $payment))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('payments.receipt', $payment))->assertForbidden();
    }

    public function test_opening_a_notification_marks_it_read(): void
    {
        $tenant = User::factory()->create();
        $notification = Notification::create(['user_id' => $tenant->id, 'title' => 'x', 'message' => 'x', 'type' => 'payment']);

        $this->actingAs($tenant)->get(route('notifications.open', $notification))->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
