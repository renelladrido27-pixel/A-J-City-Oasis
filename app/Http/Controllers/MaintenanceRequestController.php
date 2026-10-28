<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Services\MaintenanceService;
use App\Support\AccountRules;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceRequestController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $requests = match (true) {
            $request->user()->isAdmin(), $request->user()->isStaff() => MaintenanceRequest::with(['tenant', 'room'])->latest()->paginate(25),
            default => MaintenanceRequest::where('tenant_id', $request->user()->id)->with(['room'])->latest()->paginate(25),
        };

        $activeLease = $request->user()->isTenant()
            ? $request->user()->leases()->where('status', 'active')->first()
            : null;

        if ($request->user()->isTenant() || $request->user()->isAdmin() || $request->user()->isStaff()) {
            $this->notifications->markTypesRead($request->user(), ['maintenance']);
        }

        // Names to pick from as you type: anyone assigned before, plus staff
        // accounts — with their last-used phone, so picking a name fills it in.
        $assignees = $request->user()->isAdmin() || $request->user()->isStaff()
            ? MaintenanceRequest::whereNotNull('assignee_name')->latest('updated_at')->get(['assignee_name', 'assignee_phone'])
                ->map(fn ($r) => ['name' => $r->assignee_name, 'phone' => $r->assignee_phone])
                ->concat(User::where('role', 'staff')->get(['name', 'phone'])->map(fn ($u) => ['name' => $u->name, 'phone' => $u->phone]))
                ->unique(fn ($a) => mb_strtolower($a['name']))
                ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values()
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

        $request->merge(['assignee_phone' => AccountRules::normalizePhone($request->input('assignee_phone'))]);
        $validated = $request->validate([
            // Typed in: whoever does the work, with or without an account.
            'assignee_name' => ['nullable', 'string', 'max:100'],
            'assignee_phone' => [...AccountRules::phone(required: false), 'max:20'],
            // Every assignment says when the visit is, so the tenant knows.
            'scheduled_date' => ['nullable', 'required_with:assignee_name', 'date'],
        ], [
            'assignee_phone.regex' => 'Enter a valid Philippine mobile number, e.g. 09171234567.',
            'scheduled_date.required_with' => 'Pick the visit date for the person assigned.',
        ]);

        app(MaintenanceService::class)->assign(
            $maintenanceRequest,
            $validated['assignee_name'] ?? null,
            $validated['assignee_phone'] ?? null,
            $validated['scheduled_date'] ?? null,
            $request->user(),
        );

        return back()->with('status', $maintenanceRequest->isAssigned() ? 'Maintenance request assigned.' : 'Maintenance request unassigned.');
    }

    /** The tenant confirms the work is done. */
    public function resolve(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless($maintenanceRequest->tenant_id === $request->user()->id, 403);

        app(MaintenanceService::class)->resolve($maintenanceRequest);

        return back()->with('status', 'Thanks — the request is marked as done.');
    }

    /** The tenant withdraws a request that hasn't been assigned yet. */
    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless($maintenanceRequest->tenant_id === $request->user()->id, 403);

        app(MaintenanceService::class)->cancel($maintenanceRequest);

        return back()->with('status', 'Maintenance request cancelled.');
    }

    /** The tenant deletes a request they changed their mind about. */
    public function destroy(Request $request, MaintenanceRequest $maintenanceRequest): RedirectResponse
    {
        abort_unless($maintenanceRequest->tenant_id === $request->user()->id, 403);

        app(MaintenanceService::class)->delete($maintenanceRequest);

        return back()->with('status', 'Maintenance request removed.');
    }
}
