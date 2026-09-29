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
 * - If the tenant owes more than the credit, one "move_out_balance" bill is
 *   created for the difference, payable through Xendit.
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
            $settlement = $this->compute($lease, now(), $damageTotal);

            foreach ($deductions as $deduction) {
                $moveOut->deductions()->create([
                    'description' => $deduction['description'],
                    'amount' => $deduction['amount'],
                ]);
            }

            // Every open bill is closed here: covered by the deposit credit, or
            // rolled into the single balance bill below — never charged twice.
            $this->unpaidBills($lease)->each->update(['status' => 'settled']);

            $balancePayment = null;
            if ($settlement['net'] < 0) {
                $balancePayment = Payment::create([
                    'lease_id' => $lease->id,
                    'type' => 'move_out_balance',
                    'amount' => -$settlement['net'],
                    'due_date' => now()->addDays(7),
                    'status' => 'pending',
                ]);
            }

            $moveOut->update([
                'actual_move_out_date' => now(),
                'unused_rent_credit' => $settlement['unused_rent_credit'],
                'security_deposit' => $settlement['security_deposit'],
                'unpaid_dues' => $settlement['unpaid_dues'],
                'damage_total' => $settlement['damage_total'],
                'refund_amount' => max(0, $settlement['net']),
                'balance_due' => max(0, -$settlement['net']),
                'balance_payment_id' => $balancePayment?->id,
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
            'balance_due' => 'Your move-out is finalized. Deductions exceeded your security deposit — please pay the remaining ₱'.number_format($moveOut->balance_due, 2).' via Xendit.',
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

        $unusedRentCredit = $this->monthIsCovered($lease, $moveOutDate)
            ? round((max(0, $moveOutDate->daysInMonth - $moveOutDate->day) / $moveOutDate->daysInMonth) * $monthlyRate, 2)
            : 0.0;

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
     * Only credit unused days if rent for the move-out month exists (paid or
     * still owed — an owed bill is in unpaid dues). The lease's first month is
     * covered by the upfront advance.
     */
    protected function monthIsCovered(Lease $lease, Carbon $date): bool
    {
        if ($lease->start_date && $lease->start_date->isSameMonth($date)) {
            return true;
        }

        return $lease->payments()
            ->where('type', 'rent')
            ->whereIn('status', ['paid', 'pending', 'overdue'])
            ->whereYear('due_date', $date->year)
            ->whereMonth('due_date', $date->month)
            ->exists();
    }

    /**
     * @return Collection<int, Payment>
     */
    protected function unpaidBills(Lease $lease): Collection
    {
        return $lease->payments()->whereIn('status', ['pending', 'overdue'])->get();
    }
}
