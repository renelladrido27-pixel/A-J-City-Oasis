<?php

namespace Tests\Feature;

use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regression (live, Oct 26): the app showed an estimated refund of ₱466.67
 * while the admin's inspection page showed ₱564.52 for the same move-out —
 * one used the move-out date, the other "today", and both counted unused
 * rent by calendar month although rent now runs from one move-in day to the
 * next. The refund is now worked out for the move-out date, by rent period,
 * and the tenant and admin see the same number.
 */
class MoveOutUnusedRentTest extends TestCase
{
    use RefreshDatabase;

    private Lease $lease;

    protected function setUp(): void
    {
        parent::setUp();

        // Nell: moved in Oct 8 (advance covers Oct 8–Nov 7), rent due Nov 8 still unpaid.
        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->lease = Lease::factory()->create(['start_date' => '2026-10-08']);
        Payment::factory()->for($this->lease)->create(['type' => 'rent', 'due_date' => '2026-11-08', 'amount' => 3500, 'status' => 'pending']);
    }

    private function moveOutOn(string $date): MoveOut
    {
        $this->actingAs($this->lease->tenant)
            ->post(route('tenant.move-outs.store', $this->lease), ['requested_move_out_date' => $date, 'reason' => 'basta'])
            ->assertRedirect();

        return MoveOut::sole();
    }

    public function test_tenant_and_admin_see_the_same_refund_worked_out_for_the_move_out_date(): void
    {
        $moveOut = $this->moveOutOn('2026-11-26');
        // Nov 8 – Dec 7 is 30 days; Nov 27 – Dec 7 (11 days) are unused: 11/30 × ₱3,500.
        $unused = 1283.33;
        $refund = round(3500 + $unused - 3500, 2); // security + unused − the unpaid Nov 8 rent

        $this->assertEquals($refund, $moveOut->refund_amount);

        Sanctum::actingAs($this->lease->tenant);
        $this->getJson('/api/lease')->assertJsonPath('lease.move_out.refund_amount', $refund);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.move-outs.show', $moveOut))->assertOk()
            ->assertSee('₱'.number_format($unused, 2))
            ->assertSee('₱'.number_format($refund, 2))
            ->assertSee('after Nov 26, 2026');
        $this->actingAs($this->lease->tenant)->get(route('tenant.leases.show', $this->lease))
            ->assertSee('₱'.number_format($refund, 2));

        // Finalizing today (Oct 26) still uses Nov 26 — not the day the button is pressed.
        $this->actingAs($admin)->post(route('admin.move-outs.finalize', $moveOut), ['deductions' => []]);
        $moveOut->refresh();
        $this->assertEquals($unused, $moveOut->unused_rent_credit);
        $this->assertEquals($refund, $moveOut->refund_amount);
    }

    public function test_moving_out_in_the_first_month_credits_the_rest_of_the_advance(): void
    {
        $moveOut = $this->moveOutOn('2026-10-26');

        // Oct 8 – Nov 7 is 31 days; Oct 27 – Nov 7 (12 days) unused: 12/31 × ₱3,500 = ₱1,354.84.
        $this->assertEquals(round(3500 + 1354.84 - 3500, 2), $moveOut->refund_amount);
    }

    public function test_no_credit_for_a_rent_period_that_was_never_billed(): void
    {
        Payment::where('lease_id', $this->lease->id)->delete();

        $moveOut = $this->moveOutOn('2026-11-26');

        // Only the security deposit: the Nov 8 – Dec 7 rent was never charged, so nothing to give back.
        $this->assertEquals(3500, $moveOut->refund_amount);
    }
}
