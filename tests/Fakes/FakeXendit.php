<?php

namespace Tests\Fakes;

use App\Services\XenditService;

/**
 * Stands in for Xendit in tests that exercise the real (non-fake-mode) payment
 * path. Records every invoice created and lets a test decide what status
 * Xendit reports back — no network calls.
 */
class FakeXendit extends XenditService
{
    /** @var array<string, array{external_id: string, amount: float, description: string, success_url: ?string, failure_url: ?string, status: string}> */
    public array $invoices = [];

    public function __construct() {}

    public function createInvoice(
        string $externalId,
        float $amount,
        string $description,
        ?string $payerEmail = null,
        ?string $successUrl = null,
        ?string $failureUrl = null,
    ): array {
        $id = 'inv_'.(count($this->invoices) + 1);

        $this->invoices[$id] = [
            'external_id' => $externalId,
            'amount' => $amount,
            'description' => $description,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'status' => 'PENDING',
        ];

        return ['invoice_id' => $id, 'invoice_url' => "https://checkout.test/{$id}", 'status' => 'PENDING'];
    }

    public function getInvoice(string $invoiceId): array
    {
        return [
            'invoice_id' => $invoiceId,
            'invoice_url' => "https://checkout.test/{$invoiceId}",
            'status' => $this->invoices[$invoiceId]['status'] ?? 'PENDING',
        ];
    }

    public function markPaid(string $invoiceId): void
    {
        $this->invoices[$invoiceId]['status'] = 'PAID';
    }
}
