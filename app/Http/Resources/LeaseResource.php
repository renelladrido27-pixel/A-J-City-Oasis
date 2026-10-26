<?php

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'document_url' => $this->documentUrl(),
            'room' => RoomResource::make($this->whenLoaded('room')),
            // This room's own bills — a tenant renting two rooms sees each
            // room's next due, not the earliest bill of either.
            ...$this->dues(),
            'move_out' => $this->moveOutSummary(),
        ];
    }

    /** Where the tenant's move-out stands, for My rental. */
    private function moveOutSummary(): ?array
    {
        $moveOut = $this->moveOut;
        if (! $moveOut) {
            return null;
        }

        return [
            'requested_date' => $moveOut->requested_move_out_date?->toDateString(),
            'reason' => $moveOut->reason,
            // awaiting_inspection until the admin finalizes; then disbursed_manually / balance_due / no_refund.
            'status' => $moveOut->isFinalized() ? $moveOut->refund_status : 'awaiting_inspection',
            // Before inspection: the same live estimate the admin's inspection page shows.
            'refund_amount' => $moveOut->isFinalized()
                ? (float) $moveOut->refund_amount
                : max(0, app(\App\Services\MoveOutSettlementService::class)->currentEstimate($moveOut)['net']),
            'balance_due' => (float) $moveOut->balance_due,
            'balance_payment_id' => $moveOut->balance_payment_id,
        ];
    }

    /** @return array{balance: float, next_due: array{payment_id: int, date: ?string, amount: float}|null} */
    private function dues(): array
    {
        $unpaid = Payment::where('lease_id', $this->id)->whereIn('status', Payment::UNPAID)->orderBy('due_date')->get();
        $next = $unpaid->first();

        return [
            'balance' => (float) $unpaid->sum('amount'),
            'next_due' => $next ? [
                'payment_id' => $next->id,
                'date' => $next->due_date?->toDateString(),
                'amount' => (float) $next->amount,
            ] : null,
        ];
    }
}
