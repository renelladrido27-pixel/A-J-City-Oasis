<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Lease extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'tenant_id',
        'room_id',
        'start_date',
        'end_date',
        'status',
        'document',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function utilityBills(): HasMany
    {
        return $this->hasMany(UtilityBill::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function roomTransfers(): HasMany
    {
        return $this->hasMany(RoomTransfer::class);
    }

    public function moveOut(): HasOne
    {
        return $this->hasOne(MoveOut::class);
    }

    /**
     * Built from the current request's host (see RoomImage::url()) so it
     * works regardless of how the app is being served.
     */
    public function documentUrl(): ?string
    {
        return $this->document ? asset('storage/'.$this->document) : null;
    }
}
