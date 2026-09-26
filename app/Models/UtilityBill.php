<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilityBill extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'type',
        'amount',
        'due_date',
        'encoded_by',
        'status',
        'payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
