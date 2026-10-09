<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MaintenanceRequestResource;
use App\Models\MaintenanceRequest;
use App\Services\MaintenanceService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceRequestController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $requests = MaintenanceRequest::where('tenant_id', $request->user()->id)
            ->with(['room', 'assignee'])
            ->latest()
            ->get();

        $this->notifications->markTypesRead($request->user(), ['maintenance']);

        return response()->json(['requests' => MaintenanceRequestResource::collection($requests)]);
    }

    /**
     * Resolves the tenant's active lease server-side rather than requiring the
     * mobile client to know its id — the web app's equivalent route is nested
     * under /leases/{lease}, but the mobile screen only ever means "my current lease".
     */
    public function store(Request $request): JsonResponse
    {
        $lease = $request->user()->leases()->where('status', 'active')->first();

        abort_unless($lease, 422, 'You do not have an active lease.');

        $validated = $request->validate([
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $maintenanceRequest = MaintenanceRequest::create([
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

        return response()->json(['request' => MaintenanceRequestResource::make($maintenanceRequest)], 201);
    }

    /** The tenant confirms the work is done. */
    public function resolve(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        abort_unless($maintenanceRequest->tenant_id === $request->user()->id, 403);

        app(MaintenanceService::class)->resolve($maintenanceRequest);

        return response()->json(['request' => MaintenanceRequestResource::make($maintenanceRequest->load('assignee'))]);
    }

    /** The tenant withdraws a request that hasn't been assigned yet. */
    public function cancel(Request $request, MaintenanceRequest $maintenanceRequest): JsonResponse
    {
        abort_unless($maintenanceRequest->tenant_id === $request->user()->id, 403);

        app(MaintenanceService::class)->cancel($maintenanceRequest);

        return response()->json(['request' => MaintenanceRequestResource::make($maintenanceRequest->load('assignee'))]);
    }
}
