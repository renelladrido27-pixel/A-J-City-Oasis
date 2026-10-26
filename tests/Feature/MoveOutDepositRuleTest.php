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
 * The owner's example (Oct 26): moved in Oct 8, moves out the next month.
 * Rent is used up and never refunded. Paid the Nov 8 rent → both the deposit
 * and the security deposit come back; didn't pay it → the deposit pays it and
 * only the security deposit comes back. Damages come out of what's left.
 */
class MoveOutDepositRuleTest extends TestCase
{
    use RefreshDatabase;

    private Lease $lease;

    private Payment $novemberRent;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-26 09:00:00');
        $this->lease = Lease::factory()->create(['start_date' => '2026-10-08']);
        $this->novemberRent = Payment::factory()->for($this->lease)->create([
            'type' => 'rent', 'due_date' => '2026-11-08', 'amount' => 3500, 'status' => 'pending',
        ]);
    }

    private function moveOutOn(string $date): MoveOut
    {
        $this->actingAs($this->lease->tenant)
            ->post(route('tenant.move-outs.store', $this->lease), ['requested_move_out_date' => $date])
            ->assertRedirect();

        return MoveOut::sole();
    }

    private function finalize(MoveOut $moveOut, array $deductions = []): MoveOut
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.move-outs.finalize', $moveOut), ['deductions' => $deductions]);

        return $moveOut->fresh();
    }

    public function test_paid_the_second_month_gets_the_deposit_and_the_security_back(): void
    {
        $this->novemberRent->update(['status' => 'paid', 'paid_at' => now()]);
        $moveOut = $this->moveOutOn('2026-11-26');

        $this->assertEquals(7000, $moveOut->refund_amount);
        $settled = $this->finalize($moveOut);
        $this->assertEquals(3500, $settled->rent_deposit);
        $this->assertEquals(3500, $settled->security_deposit);
        $this->assertEquals(0, $settled->unused_rent_credit);
        $this->assertEquals(7000, $settled->refund_amount);
    }

    public function test_did_not_pay_the_second_month_so_the_deposit_pays_it_and_only_the_security_comes_back(): void
    {
        $moveOut = $this->moveOutOn('2026-11-26');

        // Tenant and admin see the same figure before the inspection.
        Sanctum::actingAs($this->lease->tenant);
        $this->getJson('/api/lease')->assertJsonPath('lease.move_out.refund_amount', 3500);
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.move-outs.show', $moveOut))->assertOk()
            ->assertSee('1-month deposit')
            ->assertSee('Security deposit')
            ->assertDontSee('Unused rent');

        $settled = $this->finalize($moveOut);
        $this->assertEquals(3500, $settled->unpaid_dues);
        $this->assertEquals(3500, $settled->refund_amount);
        // The November bill is closed by the deposit, not left owing.
        $this->assertSame('settled', $this->novemberRent->fresh()->status);
    }

    public function test_damages_come_out_of_what_is_left(): void
    {
        $settled = $this->finalize($this->moveOutOn('2026-11-26'), [['description' => 'Broken window', 'amount' => '500']]);

        // 3,500 deposit + 3,500 security − 3,500 unpaid rent − 500 damages.
        $this->assertEquals(3000, $settled->refund_amount);
        $this->assertSame('disbursed_manually', $settled->refund_status);
    }

    public function test_more_owed_than_both_deposits_is_settled_outside_the_system(): void
    {
        $settled = $this->finalize($this->moveOutOn('2026-11-26'), [['description' => 'Destroyed aircon', 'amount' => '5000']]);

        // 7,000 deposits − 3,500 unpaid rent − 5,000 damages = −1,500.
        $this->assertSame('balance_due', $settled->refund_status);
        $this->assertEquals(1500, $settled->balance_due);
        $this->assertEquals(0, $settled->refund_amount);
        $this->assertSame(0, $this->lease->payments()->where('type', 'move_out_balance')->count());
    }

    public function test_the_terms_and_receipts_say_rent_is_not_refunded(): void
    {
        $sections = collect(\App\Support\RentalAgreement::sections($this->lease->room))->keyBy('heading');

        $this->assertStringContainsString('Rent paid for a month is used up and is not refunded', $sections['Move-Out and Deposits']['body']);
        $this->assertStringContainsString('one-month deposit is applied to any rent or other bills still unpaid and is otherwise refunded in full', $sections['Move-Out and Deposits']['body']);
    }
}
