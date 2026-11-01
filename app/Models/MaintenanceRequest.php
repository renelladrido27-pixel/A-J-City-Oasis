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
     * The tenant can mark the work done once someone is assigned and the
     * visit day has come — not before the work could have been done.
     */
    public function canBeMarkedDone(): bool
    {
        return $this->status === 'in_progress' && $this->isAssigned() && ! $this->isBeforeVisitDay();
    }

    /** Someone is booked to come, but not until a later day. */
    public function isBeforeVisitDay(): bool
    {
        return $this->scheduled_date !== null && $this->scheduled_date->isAfter(today());
    }

    /** The tenant can remove a request any time before it is done (resolved stays on record). */
    public function isDeletable(): bool
    {
        return in_array($this->status, ['pending', 'in_progress', 'cancelled'], true);
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
        'assignee_name',
        'assignee_phone',
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

    /** Someone (typed in by the admin — not necessarily a system account) is on it. */
    public function isAssigned(): bool
    {
        return filled($this->assignee_name);
    }

    /** Old assignments pointed at an account; new ones only store the name. */
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
