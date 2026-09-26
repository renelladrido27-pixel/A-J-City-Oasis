<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'move_in_deadline' => $this->move_in_deadline?->toDateString(),
            'move_in_date' => $this->move_in_date?->toDateString(),
            'advance_amount' => (float) $this->advance_amount,
            'deposit_amount' => (float) $this->deposit_amount,
            'security_amount' => (float) $this->security_amount,
            'total_amount' => (float) $this->total_amount,
            'room' => RoomResource::make($this->whenLoaded('room')),
            'payment' => PaymentResource::make($this->whenLoaded('payments', fn () => $this->payments->firstWhere('type', 'booking_upfront'))),
        ];
    }
}
