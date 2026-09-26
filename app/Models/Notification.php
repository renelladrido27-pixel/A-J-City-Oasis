<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    /**
     * Where clicking this notification should take its recipient — the relevant
     * section for its type, resolved per the recipient's role since e.g. a tenant
     * and an admin land on different pages for the same notification type.
     */
    public function linkFor(User $user): ?string
    {
        if ($user->isTenant()) {
            return match ($this->type) {
                'booking' => route('tenant.bookings.index'),
                'payment', 'utility' => route('tenant.payments.index'),
                'lease', 'transfer', 'move_out' => route('tenant.leases.index'),
                'maintenance' => route('tenant.maintenance-requests.index'),
                'announcement' => route('tenant.announcements.index'),
                default => null,
            };
        }

        if ($user->isStaff()) {
            return match ($this->type) {
                'maintenance' => route('staff.maintenance.index'),
                default => null,
            };
        }

        if ($user->isAdmin()) {
            return match ($this->type) {
                'booking' => route('admin.bookings.index'),
                'payment' => route('admin.payments.index'),
                'utility' => route('admin.utility-bills.index'),
                'lease', 'move_out' => route('admin.leases.index'),
                'transfer' => route('admin.room-transfers.index'),
                'maintenance' => route('admin.maintenance-requests.index'),
                'announcement' => route('admin.announcements.index'),
                default => null,
            };
        }

        return null;
    }

    /**
     * The nav-item "section" this notification belongs to, for showing an unread
     * indicator dot on that nav link (e.g. a maintenance update dots the Maintenance link).
     */
    public static function tenantNavSections(): array
    {
        return [
            'bookings' => ['booking'],
            'lease' => ['lease', 'transfer', 'move_out'],
            'payments' => ['payment', 'utility'],
            'maintenance' => ['maintenance'],
            'announcements' => ['announcement'],
        ];
    }

    /**
     * Category filter options for the notifications page — same type groupings as
     * tenantNavSections(), but with a friendly label since this also serves admin/staff.
     */
    public static function categories(): array
    {
        return [
            'booking' => ['label' => 'Bookings', 'types' => ['booking']],
            'lease' => ['label' => 'Lease', 'types' => ['lease', 'transfer', 'move_out']],
            'payment' => ['label' => 'Payments', 'types' => ['payment', 'utility']],
            'maintenance' => ['label' => 'Maintenance', 'types' => ['maintenance']],
            'announcement' => ['label' => 'Announcements', 'types' => ['announcement']],
        ];
    }
}
