<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeXendit;
use Tests\TestCase;

/**
 * Two dead ends a real user hit or would hit: "419 Page Expired" when logging
 * out from a page left open too long, and a bill that could no longer be paid
 * online once the daily check had flagged it overdue.
 */
class ExpiredPageAndOverdueTest extends TestCase
{
    use RefreshDatabase;

    public function test_logging_out_never_needs_a_fresh_form_token(): void
    {
        $this->assertContains('logout', app(ValidateCsrfToken::class)->getExcludedPaths());

        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_an_expired_form_goes_back_with_a_message_instead_of_a_419_page(): void
    {
        // What the CSRF check throws when a form's token has expired.
        Route::post('/_expired-form', fn () => throw new TokenMismatchException)->middleware('web');

        $this->from('/login')->post('/_expired-form', ['email' => 'me@example.com', 'password' => 'secret'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['session' => 'That page was open for too long and expired. Please try again.'])
            ->assertSessionHasInput('email', 'me@example.com')
            ->assertSessionMissing('_old_input.password');
    }

    public function test_the_mobile_api_still_gets_a_plain_419(): void
    {
        Route::post('/_expired-form', fn () => throw new TokenMismatchException)->middleware('web');

        $this->postJson('/_expired-form')->assertStatus(419);
    }

    public function test_a_bill_flagged_overdue_can_still_be_paid_on_the_website(): void
    {
        config(['xendit.fake_mode' => false]);
        $this->app->instance(XenditService::class, new FakeXendit);
        $payment = Payment::factory()->create(['status' => 'overdue', 'due_date' => now()->subDays(40)]);
        $tenant = $payment->lease->tenant;

        $this->actingAs($tenant)->get(route('tenant.payments.index'))->assertOk()
            ->assertSee(route('payments.pay', $payment));

        $response = $this->actingAs($tenant)->post(route('payments.pay', $payment));

        $response->assertRedirect();
        $this->assertNotNull($payment->fresh()->xendit_invoice_id);
    }

    public function test_an_overdue_bill_counts_as_outstanding_and_is_payable_in_the_app(): void
    {
        config(['xendit.fake_mode' => false]);
        $this->app->instance(XenditService::class, new FakeXendit);
        $payment = Payment::factory()->create(['status' => 'overdue', 'amount' => 3500, 'due_date' => now()->subDays(40)]);
        Sanctum::actingAs($payment->lease->tenant);

        $this->getJson('/api/payments')->assertOk()
            ->assertJsonPath('outstanding_balance', 3500)
            ->assertJsonPath('next_due_payment_id', $payment->id);

        $this->postJson("/api/payments/{$payment->id}/pay")->assertOk()->assertJsonPath('status', 'redirect');
    }

    public function test_an_admin_can_record_a_walk_in_payment_for_an_overdue_bill(): void
    {
        $payment = Payment::factory()->create(['status' => 'overdue', 'due_date' => now()->subDays(40)]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.payments.record-manual', $payment))
            ->assertSessionHas('status');

        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_paid_and_refunded_bills_are_not_payable(): void
    {
        foreach (['paid', 'refunded'] as $status) {
            $payment = Payment::factory()->create(['status' => $status]);

            $this->actingAs($payment->lease->tenant)->post(route('payments.pay', $payment))->assertStatus(422);
        }
    }
}
