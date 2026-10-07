<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $requests = match (true) {
            $request->user()->isAdmin(), $request->user()->isStaff() => MaintenanceRequest::with(['tenant', 'room', 'assignee'])->latest()->paginate(25),
            default => MaintenanceRequest::where('tenant_id', $request->user()->id)->with(['room', 'assignee'])->latest()->paginate(25),
        };

        $activeLease = $request->user()->isTenant()
            ? $request->user()->leases()->where('status', 'active')->first()
            : null;

        if ($request->user()->isTenant() || $request->user()->isAdmin() || $request->user()->isStaff()) {
            $this->notifications->markTypesRead($request->user(), ['maintenance']);
        }

        $assignees = $request->user()->isAdmin() || $request->user()->isStaff()
            ? User::whereIn('role', ['admin', 'staff'])->orderBy('name')->get()
            : collect();

        return view('maintenance-requests.index', compact('requests', 'activeLease', 'assignees'));
    }

    public function create(Lease $lease): View
    {
        $this->authorize('view', $lease);

        return view('maintenance-requests.create', compact('lease'));
    }

    public function store(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('view', $lease);

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        MaintenanceRequest::create([
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'room_id' => $lease->room_id,
            'category' => $validated['category'],
            'description' => $validated['description'],
            'photo' => $request->hasFile('photo') ? $request->file('photo')->store('maintenance', 'public') : null,
            'status' => 'pending',
        ]);

        $this->notifications->notifyAdminsAndStaff(
            'New maintenance request',
            "{$lease->tenant->name} reported a {$validated['category']} issue in Room {$lease->room->room_number}.",
            'maintenance',
        );

        return redirect()->route('tenant.maintenance-requests.index')->with('status', 'Maintenance request submitted.');
    }

    public function update(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorize('update', $maintenanceRequest);

        $validated = $request->validate([
            'status' => ['required', Rule::in(MaintenanceRequest::STATUSES)],
            // Only staff/admin accounts do maintenance work.
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereIn('role', ['admin', 'staff'])],
            'scheduled_date' => ['nullable', 'date'],
        ]);

        $previousAssignee = $maintenanceRequest->assigned_to;

        $maintenanceRequest->update([
            ...$validated,
            'resolved_at' => $validated['status'] === 'resolved' ? ($maintenanceRequest->resolved_at ?? now()) : null,
        ]);
        $maintenanceRequest->load('assignee');

        // Tell the tenant who is handling it and when, not just the status.
        $message = "Your {$maintenanceRequest->category} request is now ".str_replace('_', ' ', $validated['status']).'.';
        if ($maintenanceRequest->assignee && in_array($validated['status'], ['pending', 'in_progress'], true)) {
            $message .= " Assigned to {$maintenanceRequest->assignee->name}";
            $message .= $maintenanceRequest->scheduled_date ? ', scheduled for '.$maintenanceRequest->scheduled_date->format('M d, Y').'.' : '.';
        }

        $this->notifications->notify($maintenanceRequest->tenant, 'Maintenance request updated', $message, 'maintenance');

        // Tell the staff member a job was just handed to them.
        if ($maintenanceRequest->assignee && $maintenanceRequest->assigned_to !== $previousAssignee && $maintenanceRequest->assigned_to !== $request->user()->id) {
            $this->notifications->notify(
                $maintenanceRequest->assignee,
                'Maintenance job assigned to you',
                "{$maintenanceRequest->category} in Room {$maintenanceRequest->room->room_number}"
                    .($maintenanceRequest->scheduled_date ? ', scheduled for '.$maintenanceRequest->scheduled_date->format('M d, Y') : '').'.',
                'maintenance',
            );
        }

        return back()->with('status', 'Maintenance request updated.');
    }
}
