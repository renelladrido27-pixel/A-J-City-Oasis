<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MoveOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'requested_move_out_date',
        'actual_move_out_date',
        'refund_amount',
        'unused_rent_credit',
        'security_deposit',
        'unpaid_dues',
        'damage_total',
        'balance_due',
        'balance_payment_id',
        'refund_status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_move_out_date' => 'date',
            'actual_move_out_date' => 'date',
            'refund_amount' => 'decimal:2',
            'unused_rent_credit' => 'decimal:2',
            'security_deposit' => 'decimal:2',
            'unpaid_dues' => 'decimal:2',
            'damage_total' => 'decimal:2',
            'balance_due' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function deductions(): HasMany
    {
        return $this->hasMany(MoveOutDeduction::class);
    }

    /**
     * The bill created when damages and unpaid dues exceed the deposit credit.
     */
    public function balancePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'balance_payment_id');
    }

    public function isFinalized(): bool
    {
        return $this->refund_status !== 'calculated';
    }
}
