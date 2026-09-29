<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\MoveOut;
use App\Services\MoveOutSettlementService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoveOutController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
        protected MoveOutSettlementService $settlement,
    ) {}

    public function create(Lease $lease): View
    {
        $this->authorize('view', $lease);

        return view('move-outs.create', compact('lease'));
    }

    /**
     * Tenant request. The refund shown is an estimate: damages are only known
     * after the admin inspects the room (see finalize()).
     */
    public function store(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('view', $lease);
        abort_if($lease->moveOut()->exists(), 422, 'A move-out has already been requested for this lease.');

        $validated = $request->validate([
            'requested_move_out_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $estimate = $this->settlement->estimate($lease, Carbon::parse($validated['requested_move_out_date']));

        MoveOut::create([
            'lease_id' => $lease->id,
            'requested_move_out_date' => $validated['requested_move_out_date'],
            'refund_amount' => max(0, $estimate['net']),
            'refund_status' => 'calculated',
            'reason' => $validated['reason'] ?? null,
        ]);

        $this->notifications->notifyAdmins(
            'New move-out request',
            "{$lease->tenant->name} requested to move out of Room {$lease->room->room_number} on ".Carbon::parse($validated['requested_move_out_date'])->format('M d, Y').'.',
            'move_out',
        );

        return redirect()->route('tenant.leases.show', $lease)
            ->with('status', 'Move-out request submitted. Estimated refund: ₱'.number_format(max(0, $estimate['net']), 2).' — final amount after the room inspection.');
    }

    public function index(): View
    {
        $moveOuts = MoveOut::with(['lease.tenant', 'lease.room.property'])
            ->orderByRaw("refund_status = 'calculated' desc")
            ->latest()
            ->paginate(25);

        $this->notifications->markTypesRead(auth()->user(), ['move_out']);

        return view('admin.move-outs.index', compact('moveOuts'));
    }

    /**
     * Inspection screen: settlement breakdown plus the damage deductions form.
     */
    public function show(MoveOut $moveOut): View
    {
        $moveOut->load(['lease.tenant', 'lease.room.property', 'deductions', 'balancePayment']);

        $estimate = $moveOut->isFinalized()
            ? null
            : $this->settlement->estimate($moveOut->lease, now());

        return view('admin.move-outs.show', compact('moveOut', 'estimate'));
    }

    public function finalize(Request $request, MoveOut $moveOut): RedirectResponse
    {
        $validated = $request->validate([
            'deductions' => ['array', 'max:30'],
            'deductions.*.description' => ['required_with:deductions.*.amount', 'nullable', 'string', 'max:255'],
            'deductions.*.amount' => ['required_with:deductions.*.description', 'nullable', 'numeric', 'min:0.01', 'max:1000000'],
        ], [
            'deductions.*.description.required_with' => 'Each deduction needs a description.',
            'deductions.*.amount.required_with' => 'Each deduction needs an amount.',
        ]);

        // Blank rows from the form are ignored.
        $deductions = collect($validated['deductions'] ?? [])
            ->filter(fn ($d) => filled($d['description'] ?? null) && filled($d['amount'] ?? null))
            ->values()
            ->all();

        $moveOut = $this->settlement->finalize($moveOut, $deductions);

        return redirect()->route('admin.move-outs.show', $moveOut)
            ->with('status', match ($moveOut->refund_status) {
                'balance_due' => 'Move-out finalized. A ₱'.number_format($moveOut->balance_due, 2).' balance bill was sent to the tenant.',
                'disbursed_manually' => 'Move-out finalized. Release the ₱'.number_format($moveOut->refund_amount, 2).' refund to the tenant.',
                default => 'Move-out finalized. No refund or balance due.',
            });
    }
}
