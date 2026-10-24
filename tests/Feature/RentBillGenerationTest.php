<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Generate Next Month's Rent" on the admin lease page must bill a lease
 * once per month, however many times the button is pressed.
 */
class RentBillGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generating_next_months_rent_twice_creates_only_one_bill(): void
    {
        $this->travelTo('2026-10-27 10:00:00');
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create(['start_date' => '2026-10-27']);

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))
            ->assertSessionHas('status', 'Rent payment generated, due Nov 27, 2026.');

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))
            ->assertSessionHasErrors(['rent' => 'The rent due Nov 27, 2026 was already generated for this lease.']);

        $bills = Payment::where('lease_id', $lease->id)->where('type', 'rent')->get();
        $this->assertCount(1, $bills);
        $this->assertSame('2026-11-27', $bills->first()->due_date->toDateString());
        $this->assertEquals($lease->room->monthly_rate, $bills->first()->amount);
    }

    public function test_a_bill_already_paid_for_next_month_still_blocks_a_second_one(): void
    {
        $this->travelTo('2026-10-27 10:00:00');
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create(['start_date' => '2026-10-27']);
        Payment::factory()->create([
            'lease_id' => $lease->id,
            'type' => 'rent',
            'status' => 'paid',
            'due_date' => '2026-11-27',
        ]);

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))->assertSessionHasErrors('rent');

        $this->assertSame(1, Payment::where('lease_id', $lease->id)->where('type', 'rent')->count());
    }

    public function test_rent_billed_for_other_months_or_other_leases_does_not_block_it(): void
    {
        $this->travelTo('2026-11-03 10:00:00');
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create(['start_date' => '2026-09-27']);
        $otherLease = Lease::factory()->create(['start_date' => '2026-09-27']);
        // Last month's rent on the same lease, and the next one on another lease.
        Payment::factory()->create(['lease_id' => $lease->id, 'type' => 'rent', 'due_date' => '2026-10-27']);
        Payment::factory()->create(['lease_id' => $otherLease->id, 'type' => 'rent', 'due_date' => '2026-11-27']);
        // A utility bill due on the same day is not rent.
        Payment::factory()->create(['lease_id' => $lease->id, 'type' => 'utility', 'due_date' => '2026-11-27']);

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))->assertSessionHas('status');

        $this->assertSame(2, Payment::where('lease_id', $lease->id)->where('type', 'rent')->count());
    }
}
