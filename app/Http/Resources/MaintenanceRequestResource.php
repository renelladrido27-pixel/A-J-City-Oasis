<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'description' => $this->description,
            'photo_url' => $this->photoUrl(),
            'status' => $this->status,
            // Who will do the work — shown to the tenant once assigned.
            'assigned_to' => $this->assignee ? [
                'name' => $this->assignee->name,
                'phone' => $this->assignee->phone,
            ] : null,
            'scheduled_date' => $this->scheduled_date?->toDateString(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
