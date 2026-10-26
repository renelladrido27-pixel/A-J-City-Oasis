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
 * Move-out money rules (confirmed with the owner, Oct 26):
 *
 * - Rent is used up when paid: nothing is refunded for days not stayed,
 *   even when the tenant leaves early.
 * - Of the 3-month upfront payment, the advance pays the first month. The
 *   1-month DEPOSIT pays any rent (or other bill) still unpaid at move-out and
 *   is refunded otherwise. The SECURITY DEPOSIT covers damages found at the
 *   move-out inspection; whatever is left is refunded.
 * - So: refund = deposit + security deposit − unpaid bills − damages,
 *   disbursed manually by the owner, outside the system.
 * - If the tenant owes more than both deposits, the difference is recorded as
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
                'rent_deposit' => $settlement['rent_deposit'],
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
            'balance_due' => 'Your move-out is finalized. Unpaid bills and damages exceeded your deposits by ₱'.number_format($moveOut->balance_due, 2).' — please settle this directly with the admin; it is not paid in the app.',
            default => 'Your move-out is finalized. Your deposits exactly covered the deductions; no refund or balance is due.',
        };
    }

    /**
     * @return array{rent_deposit: float, unused_rent_credit: float, security_deposit: float, unpaid_dues: float, damage_total: float, net: float}
     */
    protected function compute(Lease $lease, Carbon $moveOutDate, float $damageTotal): array
    {
        // The current room's rate: a room transfer adjusts both deposits to the
        // new room's rate (difference paid / credited at transfer time).
        $monthlyRate = (float) $lease->room->monthly_rate;
        $rentDeposit = $monthlyRate;
        $securityDeposit = $monthlyRate;

        $unpaidDues = round((float) $this->unpaidBills($lease)->sum('amount'), 2);

        return [
            'rent_deposit' => $rentDeposit,
            // Rent is never refunded for days not stayed (kept at 0 for old records).
            'unused_rent_credit' => 0.0,
            'security_deposit' => $securityDeposit,
            'unpaid_dues' => $unpaidDues,
            'damage_total' => $damageTotal,
            'net' => round($rentDeposit + $securityDeposit - $unpaidDues - $damageTotal, 2),
        ];
    }

    /**
     * @return Collection<int, Payment>
     */
    protected function unpaidBills(Lease $lease): Collection
    {
        return $lease->payments()->whereIn('status', ['pending', 'overdue'])->get();
    }
}
