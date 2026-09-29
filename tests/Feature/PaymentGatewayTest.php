<?php

namespace Tests\Feature;

use App\Mail\PaymentReceiptMail;
use App\Models\Payment;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeXendit;
use Tests\TestCase;

/**
 * The real (test-mode) Xendit path: invoices are created on Pay with the right
 * return URL for web vs mobile, and payment is confirmed by asking Xendit
 * directly because its webhook can't reach a locally served app.
 */
class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private FakeXendit $xendit;

    protected function setUp(): void
    {
        parent::setUp();

        config(['xendit.fake_mode' => false]);
        $this->xendit = new FakeXendit;
        $this->app->instance(XenditService::class, $this->xendit);
    }

    public function test_web_pay_creates_an_invoice_that_returns_to_the_success_page_for_this_payment(): void
    {
        $payment = Payment::factory()->create();
        $tenant = $payment->lease->tenant;

        $this->actingAs($tenant)->post(route('payments.pay', $payment))
            ->assertRedirect('https://checkout.test/inv_1');

        $invoice = $this->xendit->invoices['inv_1'];
        $this->assertSame(route('payments.success', ['payment' => $payment->id]), $invoice['success_url']);
        $this->assertEquals(3500, $invoice['amount']);
        $this->assertSame("Monthly rent for Room {$payment->lease->room->room_number}", $invoice['description']);
        $this->assertSame('inv_1', $payment->fresh()->xendit_invoice_id);
    }

    public function test_paying_again_resumes_the_same_invoice(): void
    {
        $payment = Payment::factory()->create();
        $tenant = $payment->lease->tenant;

        $this->actingAs($tenant)->post(route('payments.pay', $payment));
        $this->actingAs($tenant)->post(route('payments.pay', $payment))->assertRedirect('https://checkout.test/inv_1');

        $this->assertCount(1, $this->xendit->invoices);
    }

    public function test_success_page_confirms_the_payment_when_xendit_reports_it_paid(): void
    {
        Mail::fake();
        $payment = Payment::factory()->create();
        $tenant = $payment->lease->tenant;
        $this->actingAs($tenant)->post(route('payments.pay', $payment));
        $this->xendit->markPaid('inv_1');

        $this->actingAs($tenant)->get(route('payments.success', ['payment' => $payment->id]))
            ->assertOk()
            ->assertSee('View Receipt');

        $this->assertSame('paid', $payment->fresh()->status);
        Mail::assertSent(PaymentReceiptMail::class, fn ($m) => $m->hasTo($tenant->email));
    }

    public function test_success_page_leaves_an_unpaid_invoice_pending(): void
    {
        $payment = Payment::factory()->create();
        $tenant = $payment->lease->tenant;
        $this->actingAs($tenant)->post(route('payments.pay', $payment));

        $this->actingAs($tenant)->get(route('payments.success', ['payment' => $payment->id]))
            ->assertOk()
            ->assertSee('Payment Processing');

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_success_page_ignores_another_tenants_payment(): void
    {
        $payment = Payment::factory()->create();
        $this->actingAs($payment->lease->tenant)->post(route('payments.pay', $payment));
        $this->xendit->markPaid('inv_1');

        $this->actingAs(User::factory()->create())
            ->get(route('payments.success', ['payment' => $payment->id]))
            ->assertOk()
            ->assertDontSee('View Receipt');

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_a_leftover_fake_mode_invoice_is_replaced_with_a_real_one(): void
    {
        $payment = Payment::factory()->create(['xendit_invoice_id' => 'fake_leftover']);

        $this->actingAs($payment->lease->tenant)->post(route('payments.pay', $payment))
            ->assertRedirect('https://checkout.test/inv_1');

        $this->assertSame('inv_1', $payment->fresh()->xendit_invoice_id);
    }

    public function test_mobile_pay_returns_to_the_public_return_to_app_page(): void
    {
        $payment = Payment::factory()->create();
        Sanctum::actingAs($payment->lease->tenant);

        $this->postJson("/api/payments/{$payment->id}/pay")
            ->assertOk()
            ->assertJson(['status' => 'redirect', 'invoice_url' => 'https://checkout.test/inv_1']);

        $invoice = $this->xendit->invoices['inv_1'];
        $this->assertSame(route('payments.return-to-app'), $invoice['success_url']);
        $this->assertSame(route('payments.return-to-app', ['failed' => 1]), $invoice['failure_url']);

        // The phone's browser has no web session, so the landing page must be public.
        $this->get(route('payments.return-to-app'))->assertOk()->assertSee('Payment Received');
    }

    public function test_mobile_status_check_completes_a_paid_invoice(): void
    {
        $payment = Payment::factory()->create();
        Sanctum::actingAs($payment->lease->tenant);
        $this->postJson("/api/payments/{$payment->id}/pay");

        $this->postJson("/api/payments/{$payment->id}/check-status")->assertJson(['status' => 'pending']);

        $this->xendit->markPaid('inv_1');
        $this->postJson("/api/payments/{$payment->id}/check-status")->assertJson(['status' => 'paid']);
        $this->assertSame('paid', $payment->fresh()->status);
    }

    public function test_a_tenant_cannot_pay_someone_elses_payment(): void
    {
        $payment = Payment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->post(route('payments.pay', $payment))
            ->assertForbidden();

        $this->assertCount(0, $this->xendit->invoices);
    }

    public function test_webhook_rejects_a_wrong_callback_token(): void
    {
        $payment = Payment::factory()->create(['xendit_invoice_id' => 'inv_9']);

        $this->postJson('/api/webhooks/xendit', ['id' => 'inv_9', 'status' => 'PAID'], ['X-Callback-Token' => 'wrong'])
            ->assertUnauthorized();

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_webhook_with_the_right_token_marks_the_payment_paid(): void
    {
        $payment = Payment::factory()->create(['xendit_invoice_id' => 'inv_9']);

        $this->postJson('/api/webhooks/xendit', ['id' => 'inv_9', 'status' => 'PAID', 'payment_id' => 'pay_1'], ['X-Callback-Token' => 'test-callback-token'])
            ->assertOk();

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('pay_1', $payment->xendit_payment_id);
    }
}
