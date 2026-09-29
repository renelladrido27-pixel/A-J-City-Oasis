<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One itemized charge (e.g. "Broken window") deducted from the security
 * deposit at move-out inspection.
 */
class MoveOutDeduction extends Model
{
    protected $fillable = ['move_out_id', 'description', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function moveOut(): BelongsTo
    {
        return $this->belongsTo(MoveOut::class);
    }
}
