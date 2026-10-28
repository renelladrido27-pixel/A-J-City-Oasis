<?php

namespace App\Services;

use App\Models\MaintenanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Who moves a maintenance request along:
 *  - admin/staff only ASSIGN it (who does the work, and when) — the status
 *    follows from that: assigned = in progress, unassigned = pending;
 *  - the TENANT who reported it closes it, by confirming the work is done
 *    (resolved) or withdrawing a request nobody has started (cancelled).
 */
class MaintenanceService
{
    public function __construct(protected NotificationService $notifications) {}

    /**
     * Who will do the work, by name (and phone, if given) — maintenance workers
     * don't need an account. An empty name puts the request back to pending.
     */
    public function assign(MaintenanceRequest $request, ?string $name, ?string $phone, ?string $scheduledDate, User $by): void
    {
        abort_unless($request->isOpen(), 422, 'This request is already closed.');

        $name = filled($name) ? trim($name) : null;
        // A staff account with that name: use its phone when none was typed.
        $staff = $name ? User::where('role', 'staff')->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first() : null;
        $phone = filled($phone) ? $phone : $staff?->phone;
        $previousName = $request->assignee_name;
        $previousDate = $request->scheduled_date?->toDateString();

        $request->update([
            'assigned_to' => null,
            'assignee_name' => $name,
            'assignee_phone' => $name ? $phone : null,
            'scheduled_date' => $name ? $scheduledDate : null,
            'status' => $name ? 'in_progress' : 'pending',
        ]);

        if ($request->assignee_name === $previousName && $request->scheduled_date?->toDateString() === $previousDate) {
            return;
        }

        // Tell the tenant who is handling it and when — and that closing it is theirs to do.
        $message = $name
            ? "Your {$request->category} request is now in progress. Assigned to {$name}"
                .($request->scheduled_date ? ', scheduled for '.$request->scheduled_date->format('M d, Y') : '')
                .'. Once the work is done, please mark it as done.'
            : "Your {$request->category} request is waiting to be assigned again.";

        $this->notifications->notify($request->tenant, 'Maintenance request updated', $message, 'maintenance');

        // If the name belongs to a staff account, let them know in the system too.
        if ($staff && $name !== $previousName && $staff->id !== $by->id) {
            $this->notifications->notify(
                $staff,
                'Maintenance job assigned to you',
                "{$request->category} in Room {$request->room->room_number}"
                    .($request->scheduled_date ? ', scheduled for '.$request->scheduled_date->format('M d, Y') : '').'.',
                'maintenance',
            );
        }
    }

    /** The tenant confirms the work was done. */
    public function resolve(MaintenanceRequest $request): void
    {
        abort_unless($request->isOpen(), 422, 'This request is already closed.');
        abort_unless($request->isAssigned(), 422, 'Nobody has been assigned to this request yet.');

        $request->update(['status' => 'resolved', 'resolved_at' => now()]);

        $this->notifications->notifyAdminsAndStaff(
            'Maintenance request resolved',
            "{$request->tenant->name} confirmed the {$request->category} issue in Room {$request->room->room_number} is fixed.",
            'maintenance',
        );
    }

    /** The tenant withdraws a request that nobody has been assigned to yet. */
    public function cancel(MaintenanceRequest $request): void
    {
        abort_unless($request->status === 'pending', 422, 'Only a request that has not been assigned yet can be cancelled.');

        $request->update(['status' => 'cancelled']);

        $this->notifications->notifyAdminsAndStaff(
            'Maintenance request cancelled',
            "{$request->tenant->name} cancelled the {$request->category} request for Room {$request->room->room_number}.",
            'maintenance',
        );
    }

    /** The tenant changed their mind: remove the request (and its photo) altogether. */
    public function delete(MaintenanceRequest $request): void
    {
        abort_unless($request->isDeletable(), 422, 'This request is already done, so it stays on record.');

        $wasOpen = $request->isOpen();
        $assignee = $request->assignee_name;
        $summary = "{$request->tenant->name} removed the {$request->category} request for Room {$request->room->room_number}.";

        if ($request->photo) {
            Storage::disk('public')->delete($request->photo);
        }

        $request->delete();

        // Admins and staff were told about the request when it came in — tell them
        // it's gone (and, if someone was assigned, that the visit is off).
        if ($wasOpen) {
            $this->notifications->notifyAdminsAndStaff(
                'Maintenance request withdrawn',
                $summary.($assignee ? " {$assignee} no longer needs to visit." : ''),
                'maintenance',
            );
        }
    }
}
