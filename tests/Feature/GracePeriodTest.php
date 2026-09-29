<?php

namespace Tests\Feature;

use App\Mail\PaymentOverdueMail;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Locked rule: one-month grace period for rent and utility payments (backed by
 * the deposit) before a payment is treated as overdue.
 */
class GracePeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_payment_is_in_its_grace_period_for_thirty_days_after_the_due_date(): void
    {
        $notYetDue = Payment::factory()->make(['due_date' => now()->addDay()]);
        $justDue = Payment::factory()->make(['due_date' => now()->startOfDay()]);
        $day29 = Payment::factory()->make(['due_date' => now()->subDays(29)]);
        $day30 = Payment::factory()->make(['due_date' => now()->subDays(30)]);

        $this->assertFalse($notYetDue->isWithinGracePeriod());
        $this->assertFalse($notYetDue->isOverdue());

        $this->assertTrue($justDue->isWithinGracePeriod());
        $this->assertTrue($day29->isWithinGracePeriod());
        $this->assertFalse($day29->isOverdue());

        $this->assertFalse($day30->isWithinGracePeriod());
        $this->assertTrue($day30->isOverdue());
    }

    public function test_a_paid_payment_is_never_overdue(): void
    {
        $payment = Payment::factory()->make(['due_date' => now()->subDays(60), 'status' => 'paid']);

        $this->assertFalse($payment->isOverdue());
        $this->assertFalse($payment->isWithinGracePeriod());
    }

    public function test_the_daily_check_only_flags_payments_past_the_grace_period_and_emails_the_tenant(): void
    {
        Mail::fake();
        $overdue = Payment::factory()->create(['due_date' => now()->subDays(31)]);
        $inGrace = Payment::factory()->create(['due_date' => now()->subDays(10)]);
        $paidLate = Payment::factory()->create(['due_date' => now()->subDays(40), 'status' => 'paid']);

        $this->artisan('app:check-overdue-payments')->assertSuccessful();
        // MailService sends once the command finishes; run that step explicitly.
        $this->app->terminate();

        $this->assertSame('overdue', $overdue->fresh()->status);
        $this->assertSame('pending', $inGrace->fresh()->status);
        $this->assertSame('paid', $paidLate->fresh()->status);

        Mail::assertSent(PaymentOverdueMail::class, 1);
        Mail::assertSent(PaymentOverdueMail::class, fn ($m) => $m->hasTo($overdue->lease->tenant->email));
        $this->assertDatabaseHas('notifications', ['user_id' => $overdue->lease->tenant_id, 'title' => 'Payment overdue']);
    }
}
