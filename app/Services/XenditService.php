<?php

namespace App\Services;

use App\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Xendit\XenditSdkException;

class XenditService
{
    protected InvoiceApi $invoiceApi;

    public function __construct()
    {
        Configuration::setXenditKey((string) config('xendit.secret_key'));

        $this->invoiceApi = new InvoiceApi;
    }

    /**
     * Create a Xendit invoice.
     *
     * @param  string  $externalId  Unique reference, e.g. "booking-123"
     * @param  float  $amount
     * @param  string  $description
     * @param  string|null  $payerEmail
     * @param  string|null  $successUrl  Where Xendit sends the payer after paying (defaults to payments.success)
     * @param  string|null  $failureUrl  Where Xendit sends the payer on failure/expiry (defaults to payments.failure)
     * @return array{invoice_id: string, invoice_url: string, status: string}
     *
     * @throws PaymentGatewayException
     */
    public function createInvoice(
        string $externalId,
        float $amount,
        string $description,
        ?string $payerEmail = null,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array {
        if (config('xendit.fake_mode')) {
            $fakeId = 'fake_'.Str::uuid();

            return [
                'invoice_id' => $fakeId,
                'invoice_url' => route('payments.fake-complete', $fakeId),
                'status' => 'PENDING',
            ];
        }

        $request = new CreateInvoiceRequest([
            'external_id' => $externalId,
            'amount' => $amount,
            'description' => $description,
            'currency' => 'PHP',
            'invoice_duration' => 86400,
            'payer_email' => $payerEmail,
            'success_redirect_url' => $successUrl ?? route('payments.success'),
            'failure_redirect_url' => $failureUrl ?? route('payments.failure'),
        ]);

        try {
            $invoice = $this->callSdk(fn () => $this->invoiceApi->createInvoice($request));
        } catch (XenditSdkException $e) {
            Log::error('Xendit createInvoice failed', [
                'external_id' => $externalId,
                'message' => $e->getMessage(),
                'full_error' => $e->getFullError(),
            ]);

            throw new PaymentGatewayException(
                'The payment gateway is temporarily unavailable. Please try again later.',
                previous: $e,
            );
        }

        return [
            'invoice_id' => $invoice['id'],
            'invoice_url' => $invoice['invoice_url'],
            'status' => $invoice['status'],
        ];
    }

    /**
     * A payment's stored invoice id, or null if it can't be resumed — i.e. a
     * fake_ id left over from fake mode after fake mode has been switched off.
     * Callers treat null as "no invoice yet" and create a fresh one.
     */
    public function resumableInvoiceId(?string $invoiceId): ?string
    {
        if ($invoiceId && str_starts_with($invoiceId, 'fake_') && ! config('xendit.fake_mode')) {
            return null;
        }

        return $invoiceId;
    }

    /**
     * @throws PaymentGatewayException
     */
    public function getInvoice(string $invoiceId): array
    {
        if (config('xendit.fake_mode') && str_starts_with($invoiceId, 'fake_')) {
            return [
                'invoice_url' => route('payments.fake-complete', $invoiceId),
                'status' => 'PENDING',
            ];
        }

        try {
            $invoice = $this->callSdk(fn () => $this->invoiceApi->getInvoiceById($invoiceId));

            // The SDK's response model stores fields in an internal container and only
            // exposes them via ArrayAccess/getters — a plain (array) cast on the object
            // reflects its private storage, not the actual invoice fields, so it must
            // be read through array access (as createInvoice() already does above).
            return [
                'invoice_id' => $invoice['id'],
                'invoice_url' => $invoice['invoice_url'],
                'status' => $invoice['status'],
            ];
        } catch (XenditSdkException $e) {
            Log::error('Xendit getInvoiceById failed', [
                'invoice_id' => $invoiceId,
                'message' => $e->getMessage(),
                'full_error' => $e->getFullError(),
            ]);

            throw new PaymentGatewayException(
                'The payment gateway is temporarily unavailable. Please try again later.',
                previous: $e,
            );
        }
    }

    /**
     * The xendit-php SDK calls end() on a function's return value (not a variable) in
     * ObjectSerializer::deserialize(), which PHP flags as a notice ("Only variables
     * should be passed by reference"). That's harmless in the SDK's own logic, but
     * Laravel's error handler escalates it into a fatal ErrorException — this narrowly
     * suppresses notices/warnings for the duration of the SDK call only, not app-wide.
     */
    protected function callSdk(callable $call): mixed
    {
        $previousLevel = error_reporting();
        error_reporting($previousLevel & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED & ~E_STRICT);

        try {
            return $call();
        } finally {
            error_reporting($previousLevel);
        }
    }
}
