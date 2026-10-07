<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'title',
        'body',
        'audience',
        'property_id',
        'floor',
        'tenant_id',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    /**
     * Announcements visible to a given tenant: broadcast to everyone, scoped to their
     * property or floor (via their current active lease's room), or addressed to them directly.
     */
    public function scopeVisibleTo(Builder $query, User $tenant): Builder
    {
        $room = $tenant->leases()->where('status', 'active')->with('room')->first()?->room;

        return $query->where(function (Builder $q) use ($tenant, $room) {
            $q->where('audience', 'all')
                ->orWhere(fn ($q2) => $q2->where('audience', 'tenant')->where('tenant_id', $tenant->id));

            if ($room) {
                $q->orWhere(fn ($q2) => $q2->where('audience', 'property')->where('property_id', $room->property_id))
                    ->orWhere(fn ($q2) => $q2->where('audience', 'floor')
                        ->where('property_id', $room->property_id)
                        ->where('floor', $room->floor));
            }
        });
    }

    public function targetDescription(): string
    {
        return match ($this->audience) {
            'all' => 'All tenants',
            'property' => 'Property: '.($this->property?->name ?? '—'),
            'floor' => \App\Support\Floor::label($this->floor).' — '.($this->property?->name ?? '—'),
            'tenant' => 'Tenant: '.($this->tenant?->name ?? '—'),
            default => ucfirst($this->audience),
        };
    }
}
