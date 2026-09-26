<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MoveOut extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'requested_move_out_date',
        'actual_move_out_date',
        'refund_amount',
        'refund_status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_move_out_date' => 'date',
            'actual_move_out_date' => 'date',
            'refund_amount' => 'decimal:2',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
