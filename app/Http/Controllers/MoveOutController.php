<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\MoveOut;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoveOutController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function create(Lease $lease): View
    {
        $this->authorize('view', $lease);

        return view('move-outs.create', compact('lease'));
    }

    /**
     * Refund is calculated by the system; disbursement happens manually outside it.
     */
    public function store(Request $request, Lease $lease): RedirectResponse
    {
        $this->authorize('view', $lease);

        $validated = $request->validate([
            'requested_move_out_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $refund = $this->calculateRefund($lease, $validated['requested_move_out_date']);

        MoveOut::create([
            'lease_id' => $lease->id,
            'requested_move_out_date' => $validated['requested_move_out_date'],
            'refund_amount' => $refund,
            'refund_status' => 'calculated',
            'reason' => $validated['reason'] ?? null,
        ]);

        $this->notifications->notifyAdmins(
            'New move-out request',
            "{$lease->tenant->name} requested to move out of Room {$lease->room->room_number} on ".\Carbon\Carbon::parse($validated['requested_move_out_date'])->format('M d, Y').'.',
            'move_out',
        );

        return redirect()->route('tenant.leases.show', $lease)
            ->with('status', 'Move-out request submitted. Calculated refund: ₱'.number_format($refund, 2).' (disbursed manually by admin).');
    }

    /**
     * Unused prepaid rent for the current period, minus any unpaid dues,
     * plus the untouched security deposit.
     */
    protected function calculateRefund(Lease $lease, string $moveOutDate): float
    {
        $moveOutDate = \Carbon\Carbon::parse($moveOutDate);
        $monthlyRate = (float) $lease->room->monthly_rate;

        $daysRemainingInPeriod = max(0, $moveOutDate->daysInMonth - $moveOutDate->day);
        $unusedRentCredit = round(($daysRemainingInPeriod / $moveOutDate->daysInMonth) * $monthlyRate, 2);

        $unpaidDues = (float) $lease->payments()
            ->whereIn('status', ['pending', 'overdue'])
            ->sum('amount');

        $securityDeposit = $monthlyRate;

        return max(0, round($unusedRentCredit + $securityDeposit - $unpaidDues, 2));
    }

    public function complete(MoveOut $moveOut): RedirectResponse
    {
        $moveOut->update([
            'actual_move_out_date' => now(),
            'refund_status' => 'disbursed_manually',
        ]);

        $moveOut->lease->update(['status' => 'ended', 'end_date' => now()]);
        $moveOut->lease->room->update(['status' => 'vacant']);

        $this->notifications->notify(
            $moveOut->lease->tenant,
            'Move-out finalized',
            'Your move-out has been finalized. Your refund of ₱'.number_format($moveOut->refund_amount, 2).' has been disbursed by the admin.',
            'move_out',
        );

        return back()->with('status', 'Move-out finalized. Refund marked as disbursed manually.');
    }
}
