<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Payment;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Monthly rent bills. Rent is due on the 1st; next month's bill is created
 * ahead of time (daily by `app:generate-rent-bills`, or by the admin's button)
 * so a tenant always sees their next payment.
 */
class RentBillingService
{
    public function nextDueDate(): CarbonInterface
    {
        return now()->addMonth()->startOfMonth();
    }

    /**
     * Creates next month's rent bill for a lease — at most one per lease per
     * month, however often this is called.
     *
     * @return Payment|null the new bill, or null if that month was already billed
     */
    public function billNextMonth(Lease $lease): ?Payment
    {
        $dueDate = $this->nextDueDate();

        // The lease row is locked so two requests at once (a double click, or the
        // button during the daily job) can't both pass the "already billed?" check.
        return DB::transaction(function () use ($lease, $dueDate) {
            Lease::whereKey($lease->id)->lockForUpdate()->first();

            $alreadyBilled = Payment::where('lease_id', $lease->id)
                ->where('type', 'rent')
                ->whereYear('due_date', $dueDate->year)
                ->whereMonth('due_date', $dueDate->month)
                ->exists();

            if ($alreadyBilled) {
                return null;
            }

            return Payment::create([
                'lease_id' => $lease->id,
                'type' => 'rent',
                'amount' => $lease->room->monthly_rate,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);
        });
    }

    /**
     * Whether the daily job should bill this lease for next month: it must be
     * active and the tenant must still be living there when the bill falls due.
     */
    public function shouldBillNextMonth(Lease $lease): bool
    {
        $dueDate = $this->nextDueDate();

        if ($lease->status !== 'active' || $lease->start_date->gt($dueDate)) {
            return false;
        }

        if ($lease->end_date && $lease->end_date->lte($dueDate)) {
            return false;
        }

        $moveOutDate = $lease->moveOut?->requested_move_out_date;

        return ! ($moveOutDate && $moveOutDate->lte($dueDate));
    }
}
