<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoomResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->room_number,
            'property' => $this->whenLoaded('property', fn () => $this->property->name),
            'monthly_rate' => (float) $this->monthly_rate,
            'size_sqm' => $this->size_sqm !== null ? (float) $this->size_sqm : null,
            'description' => $this->description,
            'amenities' => $this->inclusions ?? [],
            'status' => $this->status,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => $img->url())->values()),
        ];
    }
}
