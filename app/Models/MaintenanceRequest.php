<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRequest extends Model
{
    use HasFactory;

    /** A finished request is "resolved" (it records resolved_at). */
    public const STATUSES = ['pending', 'in_progress', 'resolved', 'cancelled'];

    /** Still being worked on: not yet resolved or cancelled. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'in_progress'], true);
    }

    /**
     * The tenant may delete a request they changed their mind about — but
     * only before anyone has been assigned to it (or after they cancelled it).
     * Work in progress and finished work stay on record.
     */
    public function isDeletable(): bool
    {
        return in_array($this->status, ['pending', 'cancelled'], true);
    }

    protected $fillable = [
        'lease_id',
        'tenant_id',
        'room_id',
        'category',
        'description',
        'photo',
        'status',
        'assigned_to',
        'scheduled_date',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'resolved_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Built from the current request's host (see RoomImage::url()) so it
     * works regardless of how the app is being served.
     */
    public function photoUrl(): ?string
    {
        return $this->photo ? asset('storage/'.$this->photo) : null;
    }
}
