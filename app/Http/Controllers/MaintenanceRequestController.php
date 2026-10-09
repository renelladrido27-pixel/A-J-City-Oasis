<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceService;
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

    /**
     * Admin/staff assign the work (who, and when). They don't set the status:
     * it follows the assignment, and the tenant closes the request.
     */
    public function update(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        $this->authorize('update', $maintenanceRequest);

        $validated = $request->validate([
            // Only staff/admin accounts do maintenance work.
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->whereIn('role', ['admin', 'staff'])],
            'scheduled_date' => ['nullable', 'date'],
        ]);

        app(MaintenanceService::class)->assign(
            $maintenanceRequest,
            isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            $validated['scheduled_date'] ?? null,
            $request->user(),
        );

        return back()->with('status', $maintenanceRequest->assigned_to ? 'Maintenance request assigned.' : 'Maintenance request unassigned.');
    }

    /** The tenant confirms the work is done. */
    public function resolve(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless($maintenanceRequest->tenant_id === $request->user()->id, 403);

        app(MaintenanceService::class)->resolve($maintenanceRequest);

        return back()->with('status', 'Thanks — the request is marked as resolved.');
    }

    /** The tenant withdraws a request that hasn't been assigned yet. */
    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless($maintenanceRequest->tenant_id === $request->user()->id, 403);

        app(MaintenanceService::class)->cancel($maintenanceRequest);

        return back()->with('status', 'Maintenance request cancelled.');
    }
}
