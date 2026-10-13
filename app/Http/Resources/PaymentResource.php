<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'amount' => (float) $this->amount,
            'due_date' => $this->due_date?->toDateString(),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'status' => $this->status,
            'xendit_invoice_id' => $this->xendit_invoice_id,
            // The parts of the upfront booking payment (advance, deposit, security
            // deposit); empty for ordinary single-amount bills.
            'breakdown' => $this->hasBreakdown() ? $this->lines() : [],
            // The photo of the actual utility bill the admin attached, if any.
            'bill_photo_url' => $this->utilityBill?->receiptUrl(),
        ];
    }
}
