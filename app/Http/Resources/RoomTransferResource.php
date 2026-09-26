<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'reason' => $this->reason,
            'deposit_adjustment' => (float) $this->deposit_adjustment,
            'requested_at' => $this->requested_at?->toIso8601String(),
            'from_room' => $this->whenLoaded('fromRoom', fn () => $this->fromRoom->room_number),
            'to_room' => $this->whenLoaded('toRoom', fn () => $this->toRoom->room_number),
        ];
    }
}
