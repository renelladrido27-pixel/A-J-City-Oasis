<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'booking_id',
        'type',
        'amount',
        'due_date',
        'paid_at',
        'status',
        'xendit_invoice_id',
        'xendit_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function utilityBill(): HasOne
    {
        return $this->hasOne(UtilityBill::class);
    }

    public function isWithinGracePeriod(): bool
    {
        return $this->status === 'pending'
            && now()->gte($this->due_date)
            && now()->lt($this->due_date->copy()->addDays(30));
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && now()->gte($this->due_date->copy()->addDays(30));
    }

    /**
     * Line shown to the payer on Xendit's checkout page — shared by the web
     * and mobile pay flows so both invoices read the same.
     */
    public function gatewayDescription(): string
    {
        $room = ($this->booking?->room ?? $this->lease?->room)?->room_number;
        $suffix = $room ? " for Room {$room}" : '';

        return match ($this->type) {
            'booking_upfront' => "3-month upfront payment (advance, deposit, security){$suffix}",
            'rent' => "Monthly rent{$suffix}",
            'utility' => "Utility bill{$suffix}",
            'transfer_adjustment' => "Room transfer deposit adjustment{$suffix}",
            default => ucfirst(str_replace('_', ' ', $this->type)).' payment',
        };
    }
}
