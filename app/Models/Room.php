<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'room_number',
        'floor',
        'type',
        'size_sqm',
        'description',
        'inclusions',
        'monthly_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'monthly_rate' => 'decimal:2',
            'inclusions' => 'array',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function leases(): HasMany
    {
        return $this->hasMany(Lease::class);
    }

    /**
     * Whoever currently lives here — the room's active lease, if any.
     */
    public function activeLease(): HasOne
    {
        return $this->hasOne(Lease::class)->where('status', 'active')->latestOfMany();
    }

    /**
     * The booking holding a reserved room: not yet paid, or paid but not yet
     * moved in (no lease yet).
     */
    public function reservingBooking(): HasOne
    {
        return $this->hasOne(Booking::class)
            ->whereIn('status', ['pending_payment', 'confirmed'])
            ->whereDoesntHave('lease')
            ->latestOfMany();
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(RoomImage::class)->orderBy('sort_order');
    }

    public function isVacant(): bool
    {
        return $this->status === 'vacant';
    }
}
