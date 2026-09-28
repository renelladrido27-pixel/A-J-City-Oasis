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

        $invoiceId = $xendit->resumableInvoiceId($payment->xendit_invoice_id);

        if (! $invoiceId) {
            // Checkout opens in the phone's browser, which has no web session —
            // send it to the public "return to the app" page, not the auth-only
            // web success page. The app confirms the status itself on resume.
            $invoice = $xendit->createInvoice(
                externalId: $payment->booking_id ? 'booking-'.$payment->booking_id : 'payment-'.$payment->id,
                amount: (float) $payment->amount,
                description: $payment->gatewayDescription(),
                payerEmail: $payerEmail,
                successUrl: route('payments.return-to-app'),
                failureUrl: route('payments.return-to-app', ['failed' => 1]),
            );
            $payment->update(['xendit_invoice_id' => $invoice['invoice_id']]);
        } else {
            $invoice = $xendit->getInvoice($invoiceId);
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

        $xendit = app(XenditService::class);
        $invoiceId = $xendit->resumableInvoiceId($payment->xendit_invoice_id);

        abort_unless($invoiceId, 422, 'No payment has been started yet.');

        $invoice = $xendit->getInvoice($invoiceId);

        if (in_array($invoice['status'], ['PAID', 'SETTLED'], true)) {
            app(PaymentCompletionService::class)->complete($payment, $invoice['invoice_id'] ?? null);

            return ['status' => 'paid'];
        }

        return ['status' => 'pending'];
    }
}
