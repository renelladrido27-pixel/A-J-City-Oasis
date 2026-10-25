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

        $validated = $request->validate([
            'requested_move_out_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $moveOut = $this->settlement->request($lease, Carbon::parse($validated['requested_move_out_date']), $validated['reason'] ?? null);

        return redirect()->route('tenant.leases.show', $lease)
            ->with('status', 'Move-out request submitted. Estimated refund: ₱'.number_format((float) $moveOut->refund_amount, 2).' — final amount after the room inspection.');
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
                'balance_due' => 'Move-out finalized. The tenant owes ₱'.number_format($moveOut->balance_due, 2).', to be settled outside the system.',
                'disbursed_manually' => 'Move-out finalized. Release the ₱'.number_format($moveOut->refund_amount, 2).' refund to the tenant.',
                default => 'Move-out finalized. No refund or balance due.',
            });
    }
}
