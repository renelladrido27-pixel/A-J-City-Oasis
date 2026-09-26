<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'room_id',
        'booked_at',
        'move_in_deadline',
        'move_in_date',
        'status',
        'advance_amount',
        'deposit_amount',
        'security_amount',
        'total_amount',
        'agreement_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'move_in_deadline' => 'date',
            'move_in_date' => 'date',
            'advance_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'security_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'agreement_accepted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function lease(): HasOne
    {
        return $this->hasOne(Lease::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCancellableWithFullRefund(): bool
    {
        return $this->status === 'confirmed'
            && $this->move_in_date !== null
            && now()->lt($this->move_in_date);
    }
}
