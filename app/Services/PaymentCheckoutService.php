<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use App\Models\Payment;

/**
 * Returns the URL that sends a tenant straight to checkout for a payment:
 * resumes its open Xendit invoice, or creates one on first use. Shared by the
 * Pay button and by booking creation (which jumps directly to checkout).
 */
class PaymentCheckoutService
{
    public function __construct(protected XenditService $xendit) {}

    /**
     * @throws PaymentGatewayException
     */
    public function checkoutUrl(Payment $payment, string $payerEmail): string
    {
        $invoiceId = $this->xendit->resumableInvoiceId($payment->xendit_invoice_id);

        if ($invoiceId) {
            return $this->xendit->getInvoice($invoiceId)['invoice_url'];
        }

        $invoice = $this->xendit->createInvoice(
            externalId: $payment->booking_id ? 'booking-'.$payment->booking_id : 'payment-'.$payment->id,
            amount: (float) $payment->amount,
            description: $payment->gatewayDescription(),
            payerEmail: $payerEmail,
            successUrl: route('payments.success', ['payment' => $payment->id]),
        );

        $payment->update(['xendit_invoice_id' => $invoice['invoice_id']]);

        return $invoice['invoice_url'];
    }
}
