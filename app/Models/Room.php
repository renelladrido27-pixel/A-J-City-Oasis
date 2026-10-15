<?php

namespace App\Models;

use App\Support\Floor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Room extends Model
{
    use HasFactory;

    /** What A&J CITY OASIS units are (the owner's listing: "Studio Loft Type Apartment"). */
    public const DEFAULT_TYPE = 'Studio Loft Type';

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

    /**
     * "First Floor", "Second Floor"… — how the floor is shown everywhere.
     */
    public function floorLabel(): string
    {
        return Floor::label($this->floor);
    }

    /** "Studio Loft Type"; old rows saved as "standard" read "Standard". */
    public function typeLabel(): string
    {
        return ucfirst((string) $this->type);
    }

    /**
     * Room types to choose from on the room forms: the default plus whatever
     * the admin has already used.
     *
     * @return list<string>
     */
    public static function typeOptions(): array
    {
        return collect([self::DEFAULT_TYPE])
            ->merge(self::query()->distinct()->orderBy('type')->pluck('type'))
            ->filter()
            ->unique(fn ($type) => mb_strtolower($type))
            ->values()
            ->all();
    }

    /** "Room 103" typed into the room number box is saved as "103". */
    public static function normalizeNumber(string $number): string
    {
        return trim(preg_replace('/^\s*(room|rm\.?)\s*/i', '', $number));
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
