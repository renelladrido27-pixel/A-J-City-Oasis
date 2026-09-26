<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;
use App\Services\PaymentCompletionService;
use App\Services\XenditService;

/**
 * Shared "pay this payment" / "poll this payment" logic for the API's booking
 * and rent payment endpoints — both just create/poll a Xendit invoice and
 * hand off to PaymentCompletionService the same way the web app's
 * PaymentController does; only which parent resource to return afterward differs.
 */
trait HandlesPaymentGateway
{
    /**
     * @return array{status: string, invoice_url?: string}
     *
     * @throws PaymentGatewayException
     */
    protected function startOrResumePayment(Payment $payment, ?string $payerEmail): array
    {
        /** @var XenditService $xendit */
        $xendit = app(XenditService::class);

        if (! $payment->xendit_invoice_id) {
            $invoice = $xendit->createInvoice(
                externalId: $payment->booking_id ? 'booking-'.$payment->booking_id : 'payment-'.$payment->id,
                amount: (float) $payment->amount,
                description: ucfirst($payment->type).' payment',
                payerEmail: $payerEmail,
            );
            $payment->update(['xendit_invoice_id' => $invoice['invoice_id']]);
        } else {
            $invoice = $xendit->getInvoice($payment->xendit_invoice_id);
        }

        if (config('xendit.fake_mode')) {
            app(PaymentCompletionService::class)->complete($payment, 'fake_'.now()->timestamp);

            return ['status' => 'paid'];
        }

        return ['status' => 'redirect', 'invoice_url' => $invoice['invoice_url']];
    }

    /**
     * @return array{status: string}
     *
     * @throws PaymentGatewayException
     */
    protected function pollPaymentStatus(Payment $payment): array
    {
        if ($payment->status === 'paid') {
            return ['status' => 'paid'];
        }

        abort_unless($payment->xendit_invoice_id, 422, 'No payment has been started yet.');

        $invoice = app(XenditService::class)->getInvoice($payment->xendit_invoice_id);

        if (in_array($invoice['status'], ['PAID', 'SETTLED'], true)) {
            app(PaymentCompletionService::class)->complete($payment, $invoice['invoice_id'] ?? null);

            return ['status' => 'paid'];
        }

        return ['status' => 'pending'];
    }
}
