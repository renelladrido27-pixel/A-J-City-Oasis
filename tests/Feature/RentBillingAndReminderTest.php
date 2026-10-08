<?php

namespace Tests\Feature;

use App\Mail\PaymentDueSoonMail;
use App\Models\Booking;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\Notification;
use App\Models\Payment;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A tenant always sees next month's rent (the bill is created for them, not
 * only when the admin presses a button) and is reminded a week before any
 * bill is due.
 */
class RentBillingAndReminderTest extends TestCase
{
    use RefreshDatabase;

    private function rentBills(Lease $lease)
    {
        return Payment::where('lease_id', $lease->id)->where('type', 'rent')->orderBy('due_date')->get();
    }

    public function test_the_daily_job_bills_every_active_lease_for_next_month_once(): void
    {
        $this->travelTo('2026-10-08 00:00:00');
        $active = Lease::factory()->count(2)->create(['start_date' => '2026-09-01']);
        $ended = Lease::factory()->create(['start_date' => '2026-06-01', 'status' => 'ended']);

        $this->artisan('app:generate-rent-bills')->assertSuccessful();
        $this->artisan('app:generate-rent-bills')->assertSuccessful();

        foreach ($active as $lease) {
            $bills = $this->rentBills($lease);
            $this->assertCount(1, $bills);
            $this->assertSame('2026-11-01', $bills->first()->due_date->toDateString());
            $this->assertSame('pending', $bills->first()->status);
            $this->assertEquals($lease->room->monthly_rate, $bills->first()->amount);
        }
        $this->assertCount(0, $this->rentBills($ended));
    }

    public function test_each_new_month_gets_the_following_months_bill(): void
    {
        $this->travelTo('2026-10-08 00:00:00');
        $lease = Lease::factory()->create(['start_date' => '2026-09-01']);
        $this->artisan('app:generate-rent-bills');

        $this->travelTo('2026-11-01 00:00:00');
        $this->artisan('app:generate-rent-bills');

        $this->assertSame(
            ['2026-11-01', '2026-12-01'],
            $this->rentBills($lease)->map(fn ($p) => $p->due_date->toDateString())->all(),
        );
    }

    public function test_a_tenant_who_is_moving_out_before_the_due_date_is_not_billed(): void
    {
        $this->travelTo('2026-10-08 00:00:00');
        $leaving = Lease::factory()->create(['start_date' => '2026-06-01']);
        MoveOut::create(['lease_id' => $leaving->id, 'requested_move_out_date' => '2026-10-25', 'refund_amount' => 0]);
        $leavingLater = Lease::factory()->create(['start_date' => '2026-06-01']);
        MoveOut::create(['lease_id' => $leavingLater->id, 'requested_move_out_date' => '2026-11-20', 'refund_amount' => 0]);

        $this->artisan('app:generate-rent-bills');

        $this->assertCount(0, $this->rentBills($leaving));
        $this->assertCount(1, $this->rentBills($leavingLater));
    }

    public function test_a_new_lease_shows_its_next_payment_straight_away(): void
    {
        $this->travelTo('2026-10-08 09:00:00');
        $booking = Booking::factory()->confirmed()->create(['move_in_date' => '2026-10-10']);

        $lease = app(BookingService::class)->createLeaseForBooking($booking);

        $bills = $this->rentBills($lease);
        $this->assertCount(1, $bills);
        $this->assertSame('2026-11-01', $bills->first()->due_date->toDateString());

        Sanctum::actingAs($lease->tenant);
        $this->getJson('/api/payments')->assertOk()
            ->assertJsonPath('next_due_date', '2026-11-01')
            ->assertJsonPath('next_due_payment_id', $bills->first()->id);
    }

    public function test_the_tenant_is_reminded_once_a_week_before_the_due_date(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-08 08:00:00');
        $payment = Payment::factory()->create(['due_date' => '2026-11-01', 'amount' => 3500]);
        $tenant = $payment->lease->tenant;

        // Too early: more than a week to go.
        $this->artisan('app:send-payment-reminders')->assertSuccessful();
        $this->app->terminate();
        $this->assertNull($payment->fresh()->reminder_sent_at);
        Mail::assertNothingSent();

        // Exactly a week before.
        $this->travelTo('2026-10-25 08:00:00');
        $this->artisan('app:send-payment-reminders')->assertSuccessful();
        $this->app->terminate();

        $this->assertNotNull($payment->fresh()->reminder_sent_at);
        $notification = Notification::where('user_id', $tenant->id)->where('type', 'payment')->sole();
        $this->assertSame('Rent payment due in 7 days', $notification->title);
        $this->assertStringContainsString('₱3,500.00 is due on November 1, 2026', $notification->message);
        Mail::assertSent(PaymentDueSoonMail::class, fn ($mail) => $mail->hasTo($tenant->email));

        // The next days: no second reminder.
        $this->travelTo('2026-10-26 08:00:00');
        $this->artisan('app:send-payment-reminders');
        $this->app->terminate();

        $this->assertSame(1, Notification::where('user_id', $tenant->id)->count());
        Mail::assertSent(PaymentDueSoonMail::class, 1);
    }

    public function test_a_missed_day_still_sends_the_reminder_but_paid_and_past_due_bills_get_none(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-28 08:00:00');
        $missed = Payment::factory()->create(['due_date' => '2026-11-01']);
        $paid = Payment::factory()->create(['due_date' => '2026-11-01', 'status' => 'paid']);
        $pastDue = Payment::factory()->create(['due_date' => '2026-10-01']);

        $this->artisan('app:send-payment-reminders');
        $this->app->terminate();

        $this->assertNotNull($missed->fresh()->reminder_sent_at);
        $this->assertSame('Rent payment due in 4 days', Notification::where('user_id', $missed->lease->tenant_id)->sole()->title);
        $this->assertNull($paid->fresh()->reminder_sent_at);
        $this->assertNull($pastDue->fresh()->reminder_sent_at);
        Mail::assertSent(PaymentDueSoonMail::class, 1);
    }

    public function test_the_reminder_email_says_what_is_due_and_when(): void
    {
        $this->travelTo('2026-10-25 08:00:00');
        $payment = Payment::factory()->create(['due_date' => '2026-11-01', 'amount' => 3500, 'type' => 'utility']);

        $mail = new PaymentDueSoonMail($payment);

        $mail->assertHasSubject('Reminder: payment due in 7 days');
        $mail->assertSeeInHtml('₱3,500.00');
        $mail->assertSeeInHtml('November 1, 2026');
        $mail->assertSeeInHtml('utility');
        $mail->assertSeeInHtml(route('tenant.payments.index'));
    }
}
