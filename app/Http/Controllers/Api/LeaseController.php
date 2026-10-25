<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Services\MoveOutSettlementService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaseController extends Controller
{
    public function __construct(protected MoveOutSettlementService $settlement) {}

    /** The app's "Move out" form — same rules as the website's. */
    public function requestMoveOut(Request $request, Lease $lease): JsonResponse
    {
        abort_unless($lease->tenant_id === $request->user()->id, 403);

        $validated = $request->validate([
            'requested_move_out_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $moveOut = $this->settlement->request($lease, Carbon::parse($validated['requested_move_out_date']), $validated['reason'] ?? null);

        return response()->json([
            'message' => 'Move-out request submitted. Estimated refund: ₱'.number_format((float) $moveOut->refund_amount, 2).' — final amount after the room inspection.',
            'lease' => LeaseResource::make($lease->fresh(['room.property', 'room.images', 'moveOut'])),
        ], 201);
    }

    /**
     * The tenant's current lease for the mobile "My Rental" screen. A tenant
     * has at most one active lease at a time in this system, so unlike the web
     * app's paginated leases.index this just returns the single active one
     * (or the most recent lease if none is active, e.g. after move-out).
     */
    public function show(Request $request): JsonResponse
    {
        $lease = $request->user()->leases()
            ->with(['room.property', 'room.images', 'moveOut'])
            ->where('status', 'active')
            ->latest('start_date')
            ->first()
            ?? $request->user()->leases()->with(['room.property', 'room.images', 'moveOut'])->latest('start_date')->first();

        // Someone renting more than one room gets every active lease, so the
        // app can switch between them.
        $active = $request->user()->leases()->with(['room.property', 'room.images', 'moveOut'])
            ->where('status', 'active')->orderBy('start_date')->get();

        return response()->json([
            'lease' => $lease ? LeaseResource::make($lease) : null,
            'leases' => LeaseResource::collection($active),
        ]);
    }
}
