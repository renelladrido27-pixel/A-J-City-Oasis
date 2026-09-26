<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomTransferResource;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomTransferController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        $lease = $request->user()->leases()->where('status', 'active')->first();

        $availableRooms = Room::where('status', 'vacant')->with('property')->orderBy('room_number')->get();

        $transfers = $lease
            ? RoomTransfer::where('lease_id', $lease->id)->with(['fromRoom', 'toRoom'])->latest('requested_at')->get()
            : collect();

        return response()->json([
            'available_rooms' => RoomResource::collection($availableRooms),
            'requests' => RoomTransferResource::collection($transfers),
        ]);
    }

    /**
     * Deposit adjustment: tenant pays the difference for upgrades;
     * for downgrades, excess is credited/refunded manually (outside the system).
     * Same logic as the web app's RoomTransferController::store().
     */
    public function store(Request $request): JsonResponse
    {
        $lease = $request->user()->leases()->where('status', 'active')->first();

        abort_unless($lease, 422, 'You do not have an active lease.');

        $validated = $request->validate([
            'to_room_id' => ['required', 'exists:rooms,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $toRoom = Room::findOrFail($validated['to_room_id']);
        abort_unless($toRoom->isVacant(), 422, 'The selected room is not available.');

        $adjustment = (float) $toRoom->monthly_rate - (float) $lease->room->monthly_rate;

        $transfer = DB::transaction(function () use ($lease, $toRoom, $adjustment, $validated) {
            $transfer = RoomTransfer::create([
                'lease_id' => $lease->id,
                'from_room_id' => $lease->room_id,
                'to_room_id' => $toRoom->id,
                'reason' => $validated['reason'] ?? null,
                'deposit_adjustment' => $adjustment,
                'status' => 'pending',
                'requested_at' => now(),
            ]);

            if ($adjustment > 0) {
                Payment::create([
                    'lease_id' => $lease->id,
                    'type' => 'transfer_adjustment',
                    'amount' => $adjustment,
                    'due_date' => now()->addDays(7),
                    'status' => 'pending',
                ]);
            }

            return $transfer;
        });

        $this->notifications->notifyAdmins(
            'New room transfer request',
            "{$lease->tenant->name} requested to transfer from Room {$lease->room->room_number} to Room {$toRoom->room_number}.",
            'transfer',
        );

        return response()->json(['transfer' => RoomTransferResource::make($transfer->load(['fromRoom', 'toRoom']))], 201);
    }
}
