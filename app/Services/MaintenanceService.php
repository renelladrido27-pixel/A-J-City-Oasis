<?php

namespace App\Services;

use App\Models\MaintenanceRequest;
use App\Models\User;

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

    public function assign(MaintenanceRequest $request, ?int $assigneeId, ?string $scheduledDate, User $by): void
    {
        abort_unless($request->isOpen(), 422, 'This request is already closed.');

        $previousAssignee = $request->assigned_to;
        $previousDate = $request->scheduled_date?->toDateString();

        $request->update([
            'assigned_to' => $assigneeId,
            'scheduled_date' => $assigneeId ? $scheduledDate : null,
            'status' => $assigneeId ? 'in_progress' : 'pending',
        ]);
        $request->load('assignee');

        if ($request->assigned_to === $previousAssignee && $request->scheduled_date?->toDateString() === $previousDate) {
            return;
        }

        // Tell the tenant who is handling it and when — and that closing it is theirs to do.
        $message = $request->assignee
            ? "Your {$request->category} request is now in progress. Assigned to {$request->assignee->name}"
                .($request->scheduled_date ? ', scheduled for '.$request->scheduled_date->format('M d, Y') : '')
                .'. Once the work is done, please mark it as resolved.'
            : "Your {$request->category} request is waiting to be assigned again.";

        $this->notifications->notify($request->tenant, 'Maintenance request updated', $message, 'maintenance');

        // Tell the staff member a job was just handed to them.
        if ($request->assignee && $request->assigned_to !== $previousAssignee && $request->assigned_to !== $by->id) {
            $this->notifications->notify(
                $request->assignee,
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
}
