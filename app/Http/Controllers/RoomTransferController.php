<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\Payment;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RoomTransferController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $transfers = RoomTransfer::with(['lease.tenant', 'fromRoom', 'toRoom'])
            ->orderByRaw("status = 'pending' desc")
            ->latest('requested_at')
            ->paginate(25);

        $this->notifications->markTypesRead($request->user(), ['transfer']);

        return view('admin.room-transfers.index', compact('transfers'));
    }

    public function create(Lease $lease): View
    {
        $this->authorize('view', $lease);

        $rooms = Room::where('status', 'vacant')->with('property')->get();

        return view('room-transfers.create', compact('lease', 'rooms'));
    }

    /**
     * Deposit adjustment: tenant pays the difference for upgrades;
     * for downgrades, the excess is credited/refunded manually (outside the system).
     */
    public function store(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('view', $lease);

        $validated = $request->validate([
            'to_room_id' => ['required', 'exists:rooms,id'],
        ]);

        $toRoom = Room::findOrFail($validated['to_room_id']);
        abort_unless($toRoom->isVacant(), 422, 'The selected room is not available.');

        $adjustment = (float) $toRoom->monthly_rate - (float) $lease->room->monthly_rate;

        $transfer = DB::transaction(function () use ($lease, $toRoom, $adjustment) {
            $transfer = RoomTransfer::create([
                'lease_id' => $lease->id,
                'from_room_id' => $lease->room_id,
                'to_room_id' => $toRoom->id,
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

        return redirect()->route('tenant.leases.show', $lease)
            ->with('status', $adjustment > 0
                ? 'Transfer requested. Pay the deposit difference to complete it.'
                : 'Transfer requested. Any deposit credit will be handled by the admin outside the system.');
    }

    public function approve(RoomTransfer $roomTransfer): RedirectResponse
    {
        abort_unless($roomTransfer->status === 'pending', 422, 'Transfer already processed.');

        if ($roomTransfer->isUpgrade()) {
            $paid = $roomTransfer->lease->payments()
                ->where('type', 'transfer_adjustment')
                ->where('status', 'paid')
                ->exists();

            abort_unless($paid, 422, 'The deposit difference has not been paid yet.');
        }

        DB::transaction(function () use ($roomTransfer) {
            $toRoom = Room::where('id', $roomTransfer->to_room_id)->lockForUpdate()->first();

            abort_unless($toRoom->isVacant(), 422, 'The destination room is no longer available.');

            $roomTransfer->update(['status' => 'completed']);

            $roomTransfer->fromRoom->update(['status' => 'vacant']);
            $toRoom->update(['status' => 'occupied']);

            $roomTransfer->lease->update(['room_id' => $roomTransfer->to_room_id]);
        });

        $this->notifications->notify(
            $roomTransfer->lease->tenant,
            'Room transfer completed',
            "You've been transferred to Room {$roomTransfer->toRoom->room_number}.",
            'transfer',
        );

        return back()->with('status', 'Room transfer completed.');
    }

    public function reject(RoomTransfer $roomTransfer): RedirectResponse
    {
        $roomTransfer->update(['status' => 'rejected']);

        $this->notifications->notify(
            $roomTransfer->lease->tenant,
            'Room transfer rejected',
            "Your request to transfer to Room {$roomTransfer->toRoom->room_number} was rejected.",
            'transfer',
        );

        return back()->with('status', 'Room transfer rejected.');
    }
}
