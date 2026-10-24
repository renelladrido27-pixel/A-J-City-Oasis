<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Monthly rent bills. Rent falls due every month on the day the lease started
 * (the move-in date, when billing begins): moved in Oct 27 → due Nov 27,
 * Dec 27… The upfront "1 month advance" covers the first month, so the first
 * bill is one month after move-in. In shorter months a 29th–31st falls on the
 * month's last day (Jan 31 → Feb 28 → Mar 31).
 *
 * The next bill is created ahead of time (daily by `app:generate-rent-bills`,
 * on move-in, or by the admin's button) so a tenant always sees their next
 * payment.
 */
class RentBillingService
{
    /** The lease's n-th monthly due date (n = 1 is one month after move-in). */
    public function dueDateFor(Lease $lease, int $n): Carbon
    {
        return $lease->start_date->copy()->startOfDay()->addMonthsNoOverflow($n);
    }

    /** The next monthly due date after today. */
    public function nextDueDate(Lease $lease): Carbon
    {
        $start = $lease->start_date->copy()->startOfDay();
        $today = today();

        $n = max(1, ($today->year - $start->year) * 12 + $today->month - $start->month);
        while ($this->dueDateFor($lease, $n)->lte($today)) {
            $n++;
        }

        return $this->dueDateFor($lease, $n);
    }

    /**
     * Creates the lease's next rent bill — at most one per due date, however
     * often this is called.
     *
     * @return Payment|null the new bill, or null if that date was already billed
     */
    public function billNextMonth(Lease $lease): ?Payment
    {
        $dueDate = $this->nextDueDate($lease);

        // The lease row is locked so two requests at once (a double click, or the
        // button during the daily job) can't both pass the "already billed?" check.
        return DB::transaction(function () use ($lease, $dueDate) {
            Lease::whereKey($lease->id)->lockForUpdate()->first();

            $alreadyBilled = Payment::where('lease_id', $lease->id)
                ->where('type', 'rent')
                ->whereDate('due_date', $dueDate)
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
     * Whether the lease should get its next bill: it must be active and the
     * tenant must still be living there when the bill falls due.
     */
    public function shouldBillNextMonth(Lease $lease): bool
    {
        if ($lease->status !== 'active') {
            return false;
        }

        $dueDate = $this->nextDueDate($lease);

        if ($lease->end_date && $lease->end_date->lte($dueDate)) {
            return false;
        }

        $moveOutDate = $lease->moveOut?->requested_move_out_date;

        return ! ($moveOutDate && $moveOutDate->lte($dueDate));
    }
}
