<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTransfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'from_room_id',
        'to_room_id',
        'reason',
        'deposit_adjustment',
        'status',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'deposit_adjustment' => 'decimal:2',
            'requested_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function fromRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'from_room_id');
    }

    public function toRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'to_room_id');
    }

    public function isUpgrade(): bool
    {
        return $this->deposit_adjustment > 0;
    }

    public function isDowngrade(): bool
    {
        return $this->deposit_adjustment < 0;
    }
}
