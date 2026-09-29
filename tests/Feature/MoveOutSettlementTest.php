<?php

namespace Tests\Feature;

use App\Mail\MoveOutSettledMail;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Owner-confirmed rules: only the security deposit is refundable (the advance
 * and deposit months are consumed as rent). Unpaid bills and inspection
 * damages are deducted from it; anything beyond it becomes a bill payable
 * via Xendit.
 */
class MoveOutSettlementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Lease $lease;

    /** 21 of October's 31 days unused on Oct 10, at ₱3,500/month. */
    private const UNUSED = 2370.97;

    private const SECURITY = 3500.00;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-10 09:00:00');
        $this->admin = User::factory()->admin()->create();
        $this->lease = Lease::factory()->create(['start_date' => '2026-09-01']);
        // October's rent was paid, so the unused rest of the month is credited back.
        Payment::factory()->for($this->lease)->create(['due_date' => '2026-10-01', 'status' => 'paid', 'paid_at' => '2026-10-01']);
    }

    private function requestMoveOut(): MoveOut
    {
        $this->actingAs($this->lease->tenant)
            ->post(route('tenant.move-outs.store', $this->lease), ['requested_move_out_date' => '2026-10-10'])
            ->assertRedirect();

        return MoveOut::sole();
    }

    private function finalize(MoveOut $moveOut, array $deductions = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.move-outs.finalize', $moveOut), ['deductions' => $deductions]);
    }

    public function test_the_request_shows_an_estimate_of_the_security_deposit_plus_unused_rent(): void
    {
        $moveOut = $this->requestMoveOut();

        $this->assertSame('calculated', $moveOut->refund_status);
        $this->assertEquals(round(self::SECURITY + self::UNUSED, 2), $moveOut->refund_amount);
        $this->assertDatabaseHas('notifications', ['user_id' => $this->admin->id, 'type' => 'move_out']);
    }

    public function test_a_tenant_can_only_request_one_move_out_per_lease(): void
    {
        $this->requestMoveOut();

        $this->actingAs($this->lease->tenant)
            ->post(route('tenant.move-outs.store', $this->lease), ['requested_move_out_date' => '2026-10-12'])
            ->assertStatus(422);
        $this->assertDatabaseCount('move_outs', 1);
    }

    public function test_no_damages_refunds_the_security_deposit_and_unused_rent_only(): void
    {
        Mail::fake();
        $moveOut = $this->requestMoveOut();

        $this->finalize($moveOut)->assertRedirect(route('admin.move-outs.show', $moveOut));

        $moveOut->refresh();
        $this->assertSame('disbursed_manually', $moveOut->refund_status);
        // Only the security deposit — the advance and deposit months (₱7,000) are not refunded.
        $this->assertEquals(round(self::SECURITY + self::UNUSED, 2), $moveOut->refund_amount);
        $this->assertEquals(0, $moveOut->balance_due);
        $this->assertSame('ended', $this->lease->fresh()->status);
        $this->assertSame('vacant', $this->lease->room->fresh()->status);
        Mail::assertSent(MoveOutSettledMail::class, fn ($m) => $m->hasTo($this->lease->tenant->email));
    }

    public function test_damages_are_itemized_and_deducted_from_the_refund(): void
    {
        $moveOut = $this->requestMoveOut();

        $this->finalize($moveOut, [
            ['description' => 'Broken window', 'amount' => '1200'],
            ['description' => 'Repaint wall', 'amount' => '800'],
            ['description' => '', 'amount' => ''], // blank form row — ignored
        ]);

        $moveOut->refresh();
        $this->assertEquals(2000, $moveOut->damage_total);
        $this->assertEquals(round(self::SECURITY + self::UNUSED - 2000, 2), $moveOut->refund_amount);
        $this->assertEqualsCanonicalizing(['Broken window', 'Repaint wall'], $moveOut->deductions->pluck('description')->all());
        $this->assertNull($moveOut->balance_payment_id);
    }

    public function test_damages_beyond_the_deposit_create_a_balance_bill_payable_via_xendit(): void
    {
        $moveOut = $this->requestMoveOut();

        $this->finalize($moveOut, [['description' => 'Destroyed aircon', 'amount' => '9000']]);

        $moveOut->refresh();
        $owed = round(9000 - self::SECURITY - self::UNUSED, 2);
        $this->assertSame('balance_due', $moveOut->refund_status);
        $this->assertEquals(0, $moveOut->refund_amount);
        $this->assertEquals($owed, $moveOut->balance_due);

        $bill = $moveOut->balancePayment;
        $this->assertSame('move_out_balance', $bill->type);
        $this->assertSame('pending', $bill->status);
        $this->assertEquals($owed, $bill->amount);
        $this->assertSame($this->lease->id, $bill->lease_id);

        // The tenant pays it through the normal Xendit flow (fake mode in tests).
        $tenant = $this->lease->tenant;
        $redirect = $this->actingAs($tenant)->post(route('payments.pay', $bill))->headers->get('Location');
        $this->actingAs($tenant)->get($redirect)->assertRedirect(route('payments.success'));

        $this->assertSame('paid', $bill->fresh()->status);
        $this->assertSame('balance_paid', $moveOut->fresh()->refund_status);
    }

    public function test_unpaid_bills_are_settled_against_the_deposit_not_charged_twice(): void
    {
        $utility = Payment::factory()->for($this->lease)->create(['type' => 'utility', 'amount' => 1500, 'due_date' => '2026-10-05']);
        $moveOut = $this->requestMoveOut();
        $this->assertEquals(round(self::SECURITY + self::UNUSED - 1500, 2), $moveOut->refund_amount);

        $this->finalize($moveOut);

        $this->assertSame('settled', $utility->fresh()->status);
        $this->assertEquals(round(self::SECURITY + self::UNUSED - 1500, 2), $moveOut->fresh()->refund_amount);
        $this->assertSame(0, $this->lease->payments()->whereIn('status', ['pending', 'overdue'])->count());
    }

    public function test_no_unused_rent_credit_when_the_move_out_month_was_never_billed(): void
    {
        Payment::where('lease_id', $this->lease->id)->delete();
        $moveOut = $this->requestMoveOut();

        $this->finalize($moveOut);

        $this->assertEquals(0, $moveOut->fresh()->unused_rent_credit);
        $this->assertEquals(self::SECURITY, $moveOut->fresh()->refund_amount);
    }

    public function test_a_move_out_cannot_be_finalized_twice(): void
    {
        $moveOut = $this->requestMoveOut();
        $this->finalize($moveOut);

        $this->finalize($moveOut, [['description' => 'Late charge', 'amount' => '500']])->assertStatus(422);

        $this->assertDatabaseCount('move_out_deductions', 0);
    }

    public function test_each_deduction_needs_a_description_and_amount(): void
    {
        $moveOut = $this->requestMoveOut();

        $this->finalize($moveOut, [['description' => 'Broken lock', 'amount' => '']])
            ->assertSessionHasErrors('deductions.0.amount');

        $this->assertFalse($moveOut->fresh()->isFinalized());
    }

    public function test_only_admins_can_finalize(): void
    {
        $moveOut = $this->requestMoveOut();

        $this->actingAs($this->lease->tenant)->post(route('admin.move-outs.finalize', $moveOut))->assertForbidden();
        $this->actingAs(User::factory()->staff()->create())->post(route('admin.move-outs.finalize', $moveOut))->assertForbidden();

        $this->assertFalse($moveOut->fresh()->isFinalized());
    }

    public function test_admin_pages_list_and_show_the_inspection_form(): void
    {
        $moveOut = $this->requestMoveOut();

        $this->actingAs($this->admin)->get(route('admin.move-outs.index'))
            ->assertOk()->assertSee($this->lease->tenant->name)->assertSee('Inspect &amp; Finalize', false);
        $this->actingAs($this->admin)->get(route('admin.move-outs.show', $moveOut))
            ->assertOk()->assertSee('Room Inspection')->assertSee('Add deduction');
    }
}
