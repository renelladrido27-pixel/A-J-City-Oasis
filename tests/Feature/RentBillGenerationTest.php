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
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create();
        $nextMonth = now()->addMonth()->startOfMonth();

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))
            ->assertSessionHas('status', 'Rent payment generated for '.$nextMonth->format('F Y').'.');

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))
            ->assertSessionHasErrors(['rent' => 'Rent for '.$nextMonth->format('F Y').' was already generated for this lease.']);

        $bills = Payment::where('lease_id', $lease->id)->where('type', 'rent')->get();
        $this->assertCount(1, $bills);
        $this->assertTrue($bills->first()->due_date->isSameDay($nextMonth));
        $this->assertEquals($lease->room->monthly_rate, $bills->first()->amount);
    }

    public function test_a_bill_already_paid_for_next_month_still_blocks_a_second_one(): void
    {
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create();
        Payment::factory()->create([
            'lease_id' => $lease->id,
            'type' => 'rent',
            'status' => 'paid',
            'due_date' => now()->addMonth()->startOfMonth(),
        ]);

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))->assertSessionHasErrors('rent');

        $this->assertSame(1, Payment::where('lease_id', $lease->id)->where('type', 'rent')->count());
    }

    public function test_rent_billed_for_other_months_or_other_leases_does_not_block_it(): void
    {
        $admin = User::factory()->admin()->create();
        $lease = Lease::factory()->create();
        $otherLease = Lease::factory()->create();
        // This month's rent on the same lease, and next month's on another lease.
        Payment::factory()->create(['lease_id' => $lease->id, 'type' => 'rent', 'due_date' => now()->startOfMonth()]);
        Payment::factory()->create(['lease_id' => $otherLease->id, 'type' => 'rent', 'due_date' => now()->addMonth()->startOfMonth()]);
        // A utility bill due next month is not rent.
        Payment::factory()->create(['lease_id' => $lease->id, 'type' => 'utility', 'due_date' => now()->addMonth()->startOfMonth()]);

        $this->actingAs($admin)->post(route('admin.leases.generate-rent', $lease))->assertSessionHas('status');

        $this->assertSame(2, Payment::where('lease_id', $lease->id)->where('type', 'rent')->count());
    }
}
