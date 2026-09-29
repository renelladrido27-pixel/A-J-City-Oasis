<?php

namespace Tests\Feature;

use App\Mail\BookingConfirmedMail;
use App\Mail\LeaseStartedMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The locked booking rules: 3-month upfront payment, move-in date within 7
 * days, billing starts on the move-in date, full refund before move-in.
 */
class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    private function book(User $tenant, Room $room, ?string $moveIn = null)
    {
        return $this->actingAs($tenant)->post(route('tenant.bookings.store', $room), [
            'move_in_date' => $moveIn ?? now()->addDays(3)->toDateString(),
            'agreed_to_terms' => '1',
        ]);
    }

    public function test_booking_requires_three_months_upfront_and_reserves_the_room(): void
    {
        $tenant = User::factory()->create();
        $room = Room::factory()->create(['monthly_rate' => 4200]);

        $this->book($tenant, $room)->assertRedirect();

        $booking = Booking::sole();
        $this->assertSame('pending_payment', $booking->status);
        $this->assertEquals(4200, $booking->advance_amount);
        $this->assertEquals(4200, $booking->deposit_amount);
        $this->assertEquals(4200, $booking->security_amount);
        $this->assertEquals(12600, $booking->total_amount);
        $this->assertSame('reserved', $room->fresh()->status);

        $payment = Payment::sole();
        $this->assertSame('booking_upfront', $payment->type);
        $this->assertEquals(12600, $payment->amount);
        $this->assertSame('pending', $payment->status);
    }

    public function test_confirming_a_booking_goes_straight_to_the_payment_page(): void
    {
        $tenant = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->book($tenant, $room);

        // Fake mode (phpunit.xml): the "payment page" is the simulated completion route.
        $payment = Payment::sole();
        $this->assertNotNull($payment->xendit_invoice_id);
        $response->assertRedirect(route('payments.fake-complete', $payment->xendit_invoice_id));
    }

    public function test_if_the_payment_page_cannot_open_the_room_stays_reserved_and_pay_can_be_retried(): void
    {
        $this->app->instance(\App\Services\XenditService::class, new class extends \App\Services\XenditService
        {
            public function __construct() {}

            public function createInvoice(string $externalId, float $amount, string $description, ?string $payerEmail = null, ?string $successUrl = null, ?string $failureUrl = null): array
            {
                throw new \App\Exceptions\PaymentGatewayException('The payment gateway is temporarily unavailable.');
            }
        });
        $tenant = User::factory()->create();
        $room = Room::factory()->create();

        $response = $this->book($tenant, $room);
        $booking = Booking::sole();

        $response->assertRedirect(route('tenant.bookings.show', $booking))->assertSessionHasErrors('gateway');
        $this->assertSame('reserved', $room->fresh()->status);
        $this->assertNull(Payment::sole()->xendit_invoice_id);
    }

    public function test_move_in_date_must_be_within_seven_days(): void
    {
        $tenant = User::factory()->create();
        $room = Room::factory()->create();

        $this->book($tenant, $room, now()->addDays(8)->toDateString())
            ->assertSessionHasErrors('move_in_date');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame('vacant', $room->fresh()->status);
    }

    public function test_agreement_must_be_accepted(): void
    {
        $tenant = User::factory()->create();
        $room = Room::factory()->create();

        $this->actingAs($tenant)
            ->post(route('tenant.bookings.store', $room), ['move_in_date' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors('agreed_to_terms');
    }

    public function test_a_room_that_is_not_vacant_cannot_be_booked(): void
    {
        $tenant = User::factory()->create();
        $room = Room::factory()->create(['status' => 'reserved']);

        $this->book($tenant, $room)->assertStatus(422);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_paying_confirms_the_booking_and_starts_the_lease_on_the_move_in_date(): void
    {
        Mail::fake();
        $tenant = User::factory()->create();
        $room = Room::factory()->create();
        $moveIn = now()->addDays(5)->toDateString();

        // Booking redirects straight to checkout; in fake mode that's the simulated completion route.
        $checkout = $this->book($tenant, $room, $moveIn)->headers->get('Location');
        $payment = Payment::sole();
        $this->actingAs($tenant)->get($checkout)->assertRedirect(route('payments.success'));

        $booking = Booking::sole();
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertSame('occupied', $room->fresh()->status);

        $lease = $booking->lease;
        $this->assertNotNull($lease);
        $this->assertSame('active', $lease->status);
        // Billing starts on the scheduled move-in date, not the payment date.
        $this->assertSame($moveIn, $lease->start_date->toDateString());

        Mail::assertSent(BookingConfirmedMail::class, fn ($m) => $m->hasTo($tenant->email));
        Mail::assertSent(LeaseStartedMail::class, fn ($m) => $m->hasTo($tenant->email));
    }

    public function test_lease_is_created_once_a_paid_booking_picks_its_move_in_date(): void
    {
        $booking = Booking::factory()->confirmed()->create(['move_in_date' => null]);

        app(BookingService::class)->setMoveInDate($booking, now()->addDays(2)->toDateString());

        $this->assertNotNull($booking->fresh()->lease);
        $this->assertSame('occupied', $booking->room->fresh()->status);
    }

    public function test_move_in_date_after_the_deadline_is_rejected(): void
    {
        $booking = Booking::factory()->confirmed()->create(['move_in_date' => null]);

        $this->actingAs($booking->tenant)
            ->put(route('tenant.bookings.move-in-date', $booking), ['move_in_date' => now()->addDays(10)->toDateString()])
            ->assertStatus(422);

        $this->assertNull($booking->fresh()->move_in_date);
    }

    public function test_cancelling_before_move_in_gives_a_full_refund_and_frees_the_room(): void
    {
        $booking = Booking::factory()->confirmed()->create(['move_in_date' => now()->addDays(3)]);
        $payment = Payment::create([
            'booking_id' => $booking->id, 'type' => 'booking_upfront', 'amount' => 10500,
            'due_date' => now()->addDays(7), 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->actingAs($booking->tenant)->post(route('tenant.bookings.cancel', $booking))->assertRedirect();

        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('vacant', $booking->room->fresh()->status);
    }

    public function test_cannot_cancel_for_a_refund_on_or_after_the_move_in_date(): void
    {
        $booking = Booking::factory()->confirmed()->create(['move_in_date' => now()->startOfDay()]);

        $this->actingAs($booking->tenant)->post(route('tenant.bookings.cancel', $booking))->assertStatus(422);

        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_a_tenant_cannot_cancel_someone_elses_booking(): void
    {
        $booking = Booking::factory()->confirmed()->create(['move_in_date' => now()->addDays(3)]);

        $this->actingAs(User::factory()->create())
            ->post(route('tenant.bookings.cancel', $booking))
            ->assertForbidden();
    }

    public function test_unpaid_bookings_expire_after_the_seven_day_window(): void
    {
        $stale = Booking::factory()->create(['move_in_deadline' => now()->subDay()]);
        $fresh = Booking::factory()->create(['move_in_deadline' => now()->addDays(2)]);

        $this->artisan('app:expire-stale-bookings')->assertSuccessful();

        $this->assertSame('expired', $stale->fresh()->status);
        $this->assertSame('vacant', $stale->room->fresh()->status);
        $this->assertSame('pending_payment', $fresh->fresh()->status);
        $this->assertSame('reserved', $fresh->room->fresh()->status);
    }
}
