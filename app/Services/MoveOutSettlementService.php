<?php

namespace App\Services;

use App\Mail\MoveOutSettledMail;
use App\Models\Lease;
use App\Models\MoveOut;
use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Move-out money rules (confirmed with the owner):
 *
 * - Of the 3-month upfront payment, the advance and the deposit are consumed
 *   as rent; only the SECURITY DEPOSIT is refundable.
 * - The deposit credit (security deposit + rent already paid for the unused
 *   rest of the move-out month) is applied to unpaid bills and to damage
 *   deductions recorded at the move-out inspection.
 * - Credit left over is refunded — disbursed manually, outside the system.
 * - If the tenant owes more than the credit, the difference is recorded as
 *   the balance due and settled with the owner outside the system (no bill
 *   in the app or the website).
 */
class MoveOutSettlementService
{
    public function __construct(protected NotificationService $notifications) {}

    /**
     * What the tenant sees when requesting a move-out: before inspection, so
     * no damages yet.
     *
     * @return array{unused_rent_credit: float, security_deposit: float, unpaid_dues: float, damage_total: float, net: float}
     */
    public function estimate(Lease $lease, Carbon $moveOutDate): array
    {
        return $this->compute($lease, $moveOutDate, 0);
    }

    /** The estimate for a requested move-out, as it stands now (bills may have changed since). */
    public function currentEstimate(MoveOut $moveOut): array
    {
        return $this->estimate($moveOut->lease, $moveOut->requested_move_out_date->copy());
    }

    /**
     * The tenant asks to move out (website or app). The refund saved here is an
     * estimate: damages are only known after the admin inspects the room.
     */
    public function request(Lease $lease, Carbon $moveOutDate, ?string $reason): MoveOut
    {
        return DB::transaction(function () use ($lease, $moveOutDate, $reason) {
            Lease::whereKey($lease->id)->lockForUpdate()->first();
            abort_unless($lease->status === 'active', 422, 'This lease is not active.');
            abort_if($lease->moveOut()->exists(), 422, 'A move-out has already been requested for this lease.');

            $estimate = $this->estimate($lease, $moveOutDate);

            $moveOut = MoveOut::create([
                'lease_id' => $lease->id,
                'requested_move_out_date' => $moveOutDate->toDateString(),
                'refund_amount' => max(0, $estimate['net']),
                'refund_status' => 'calculated',
                'reason' => $reason,
            ]);

            $this->notifications->notifyAdmins(
                'New move-out request',
                "{$lease->tenant->name} requested to move out of Room {$lease->room->room_number} on ".$moveOutDate->format('M d, Y').'.',
                'move_out',
            );

            return $moveOut;
        });
    }

    /**
     * Admin finalizes after inspecting the room.
     *
     * @param  array<int, array{description: string, amount: float|string}>  $deductions
     */
    public function finalize(MoveOut $moveOut, array $deductions): MoveOut
    {
        $moveOut = DB::transaction(function () use ($moveOut, $deductions) {
            $moveOut = MoveOut::whereKey($moveOut->id)->lockForUpdate()->firstOrFail();
            abort_if($moveOut->isFinalized(), 422, 'This move-out has already been finalized.');

            $lease = $moveOut->lease;
            $damageTotal = round(collect($deductions)->sum(fn ($d) => (float) $d['amount']), 2);
            // The tenant's move-out date — the same date their estimate used —
            // not the day the admin happens to press Finalize.
            $settlement = $this->compute($lease, $moveOut->requested_move_out_date->copy(), $damageTotal);

            foreach ($deductions as $deduction) {
                $moveOut->deductions()->create([
                    'description' => $deduction['description'],
                    'amount' => $deduction['amount'],
                ]);
            }

            // Every open bill is closed here: covered by the deposit credit, or
            // part of the balance settled outside the system — never charged twice.
            $this->unpaidBills($lease)->each->update(['status' => 'settled']);

            $moveOut->update([
                'actual_move_out_date' => now(),
                'unused_rent_credit' => $settlement['unused_rent_credit'],
                'security_deposit' => $settlement['security_deposit'],
                'unpaid_dues' => $settlement['unpaid_dues'],
                'damage_total' => $settlement['damage_total'],
                'refund_amount' => max(0, $settlement['net']),
                'balance_due' => max(0, -$settlement['net']),
                'refund_status' => match (true) {
                    $settlement['net'] > 0 => 'disbursed_manually',
                    $settlement['net'] < 0 => 'balance_due',
                    default => 'no_refund',
                },
            ]);

            $lease->update(['status' => 'ended', 'end_date' => now()]);
            $lease->room->update(['status' => 'vacant']);

            $this->notifications->notify($lease->tenant, 'Move-out finalized', $this->summary($moveOut), 'move_out');
            app(MailService::class)->send($lease->tenant->email, new MoveOutSettledMail($moveOut));

            return $moveOut;
        });

        return $moveOut->fresh(['deductions', 'balancePayment', 'lease.tenant', 'lease.room']);
    }

    public function summary(MoveOut $moveOut): string
    {
        return match ($moveOut->refund_status) {
            'disbursed_manually' => 'Your move-out is finalized. Refund of ₱'.number_format($moveOut->refund_amount, 2).' will be released by the admin.',
            'balance_due' => 'Your move-out is finalized. Deductions exceeded your security deposit by ₱'.number_format($moveOut->balance_due, 2).' — please settle this directly with the admin; it is not paid in the app.',
            default => 'Your move-out is finalized. Your security deposit fully covered the deductions; no refund or balance is due.',
        };
    }

    /**
     * @return array{unused_rent_credit: float, security_deposit: float, unpaid_dues: float, damage_total: float, net: float}
     */
    protected function compute(Lease $lease, Carbon $moveOutDate, float $damageTotal): array
    {
        $monthlyRate = (float) $lease->room->monthly_rate;

        // The current room's rate: a room transfer adjusts the deposit to the
        // new room's rate (difference paid / credited at transfer time).
        $securityDeposit = $monthlyRate;

        $unusedRentCredit = $this->unusedRentCredit($lease, $moveOutDate, $monthlyRate);

        $unpaidDues = round((float) $this->unpaidBills($lease)->sum('amount'), 2);

        return [
            'unused_rent_credit' => $unusedRentCredit,
            'security_deposit' => $securityDeposit,
            'unpaid_dues' => $unpaidDues,
            'damage_total' => $damageTotal,
            'net' => round($unusedRentCredit + $securityDeposit - $unpaidDues - $damageTotal, 2),
        ];
    }

    /**
     * Rent runs from one due day to the next (moved in Oct 8 → Oct 8–Nov 7,
     * Nov 8–Dec 7…), so the credit is the days after the move-out date up to
     * the end of that rent period, as a share of the period. Only credited when
     * that period's rent exists (paid, or still owed — an owed bill is in the
     * unpaid dues); the first period is covered by the upfront advance.
     */
    protected function unusedRentCredit(Lease $lease, Carbon $moveOutDate, float $monthlyRate): float
    {
        $billing = app(RentBillingService::class);
        $date = $moveOutDate->copy()->startOfDay();
        $start = $lease->start_date?->copy()->startOfDay();
        if (! $start || $date->lt($start)) {
            return 0.0;
        }

        // The rent period the move-out date falls in: [periodStart, nextStart).
        $n = max(0, ($date->year - $start->year) * 12 + $date->month - $start->month);
        while ($n > 0 && $billing->dueDateFor($lease, $n)->gt($date)) {
            $n--;
        }
        while ($billing->dueDateFor($lease, $n + 1)->lte($date)) {
            $n++;
        }
        $periodStart = $n === 0 ? $start : $billing->dueDateFor($lease, $n);
        $nextStart = $billing->dueDateFor($lease, $n + 1);

        $covered = $n === 0 || $lease->payments()
            ->where('type', 'rent')
            ->whereIn('status', ['paid', 'pending', 'overdue'])
            ->whereDate('due_date', $periodStart)
            ->exists();
        if (! $covered) {
            return 0.0;
        }

        $periodDays = (int) $periodStart->diffInDays($nextStart);
        $unusedDays = max(0, (int) $date->diffInDays($nextStart) - 1);

        return round($unusedDays / $periodDays * $monthlyRate, 2);
    }

    /**
     * @return Collection<int, Payment>
     */
    protected function unpaidBills(Lease $lease): Collection
    {
        return $lease->payments()->whereIn('status', ['pending', 'overdue'])->get();
    }
}
