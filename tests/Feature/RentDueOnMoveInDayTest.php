<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Lease;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\RentBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rent falls due every month on the move-in day, not on the 1st:
 * moved in Oct 27 → due Nov 27, Dec 27…
 */
class RentDueOnMoveInDayTest extends TestCase
{
    use RefreshDatabase;

    private function rentDates(Lease $lease): array
    {
        return Payment::where('lease_id', $lease->id)->where('type', 'rent')->orderBy('due_date')
            ->get()->map(fn ($p) => $p->due_date->toDateString())->all();
    }

    public function test_booked_and_moved_in_on_oct_27_means_rent_is_due_nov_27(): void
    {
        $this->travelTo('2026-10-27 14:00:00');
        $booking = Booking::factory()->confirmed()->create(['move_in_date' => '2026-10-27']);

        $lease = app(BookingService::class)->createLeaseForBooking($booking);

        $this->assertSame(['2026-11-27'], $this->rentDates($lease));
    }

    public function test_each_month_follows_on_the_same_day(): void
    {
        $this->travelTo('2026-10-27 14:00:00');
        $lease = Lease::factory()->create(['start_date' => '2026-10-27']);

        $this->artisan('app:generate-rent-bills')->assertSuccessful();
        // Running again the same day changes nothing.
        $this->artisan('app:generate-rent-bills')->assertSuccessful();
        $this->assertSame(['2026-11-27'], $this->rentDates($lease));

        // The day before it's due, the next one isn't made yet…
        $this->travelTo('2026-11-26 09:00:00');
        $this->artisan('app:generate-rent-bills')->assertSuccessful();
        $this->assertSame(['2026-11-27'], $this->rentDates($lease));

        // …on the due day, December's appears, on the 27th again.
        $this->travelTo('2026-11-27 09:00:00');
        $this->artisan('app:generate-rent-bills')->assertSuccessful();
        $this->assertSame(['2026-11-27', '2026-12-27'], $this->rentDates($lease));
    }

    public function test_a_31st_falls_on_the_last_day_of_shorter_months(): void
    {
        $lease = Lease::factory()->make(['start_date' => '2026-12-31']);
        $billing = app(RentBillingService::class);

        $this->assertSame(
            ['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'],
            array_map(fn ($n) => $billing->dueDateFor($lease, $n)->toDateString(), [1, 2, 3, 4]),
        );

        $this->travelTo('2027-02-01 09:00:00');
        $this->assertSame('2027-02-28', $billing->nextDueDate($lease)->toDateString());
    }

    public function test_bills_on_the_1st_move_to_the_move_in_day(): void
    {
        $this->travelTo('2026-10-24 09:00:00');
        $oct27 = Lease::factory()->create(['start_date' => '2026-10-27']);
        $oct15 = Lease::factory()->create(['start_date' => '2026-10-15']);
        $unpaid = Payment::factory()->create([
            'lease_id' => $oct27->id, 'type' => 'rent', 'due_date' => '2026-11-01', 'status' => 'pending', 'reminder_sent_at' => now(),
        ]);
        $paid = Payment::factory()->create([
            'lease_id' => $oct15->id, 'type' => 'rent', 'due_date' => '2026-11-01', 'status' => 'paid', 'paid_at' => now(),
        ]);
        $utility = Payment::factory()->create(['lease_id' => $oct15->id, 'type' => 'utility', 'due_date' => '2026-11-01']);

        (require database_path('migrations/2026_10_24_100000_move_rent_due_dates_to_the_move_in_day.php'))->up();

        $this->assertSame('2026-11-27', $unpaid->fresh()->due_date->toDateString());
        // Its 7-day reminder goes out again for the new date.
        $this->assertNull($unpaid->fresh()->reminder_sent_at);
        $this->assertSame('2026-11-15', $paid->fresh()->due_date->toDateString());
        $this->assertSame('paid', $paid->fresh()->status);
        $this->assertSame('2026-11-01', $utility->fresh()->due_date->toDateString());

        // The paid one counts: no second November bill for that lease.
        $this->travelTo('2026-11-03 09:00:00');
        $this->artisan('app:generate-rent-bills')->assertSuccessful();
        $this->assertSame(['2026-11-15'], $this->rentDates($oct15));
    }

    public function test_the_terms_say_when_rent_is_due(): void
    {
        $lease = Lease::factory()->create();

        $rent = collect(\App\Support\RentalAgreement::sections($lease->room))->firstWhere('heading', 'Rent');

        $this->assertStringContainsString('a move-in on October 27 means rent is due November 27, December 27', $rent['body']);
    }
}
