<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'booking_id',
        'type',
        'amount',
        'due_date',
        'paid_at',
        'reminder_sent_at',
        'status',
        'xendit_invoice_id',
        'xendit_payment_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function utilityBill(): HasOne
    {
        return $this->hasOne(UtilityBill::class);
    }

    /**
     * Statuses a tenant still owes. A bill the daily check has flagged
     * "overdue" is owed just as much as a "pending" one — it must stay payable
     * and stay in the outstanding balance.
     */
    public const UNPAID = ['pending', 'overdue'];

    public function isPayable(): bool
    {
        return in_array($this->status, self::UNPAID, true);
    }

    public function isWithinGracePeriod(): bool
    {
        return $this->status === 'pending'
            && now()->gte($this->due_date)
            && now()->lt($this->due_date->copy()->addDays(30));
    }

    public function isOverdue(): bool
    {
        return $this->status === 'overdue'
            || ($this->status === 'pending' && now()->gte($this->due_date->copy()->addDays(30)));
    }

    /**
     * What a payment is made up of. The upfront booking payment is three
     * amounts paid at once (advance, deposit, security deposit); everything
     * else is a single line.
     *
     * @return list<array{label: string, note: ?string, amount: float}>
     */
    public function lines(): array
    {
        if ($this->type === 'booking_upfront' && $this->booking) {
            return [
                ['label' => '1 month advance', 'note' => "Your first month's rent", 'amount' => (float) $this->booking->advance_amount],
                ['label' => '1 month deposit', 'note' => 'Refunded at move-out if all rent is paid', 'amount' => (float) $this->booking->deposit_amount],
                ['label' => 'Security deposit', 'note' => 'Refundable at move-out, less any damages', 'amount' => (float) $this->booking->security_amount],
            ];
        }

        return [['label' => ucfirst(str_replace('_', ' ', $this->type)), 'note' => null, 'amount' => (float) $this->amount]];
    }

    /**
     * The date printed on the receipt: when this bill was due — or, for the
     * upfront payment, when the first monthly rent falls due (one month
     * after move-in; rent then recurs on that day).
     *
     * @return array{label: string, date: \Carbon\CarbonInterface}|null
     */
    public function receiptDueDate(): ?array
    {
        if ($this->type === 'booking_upfront') {
            $moveIn = $this->booking?->move_in_date;

            return $moveIn ? ['label' => 'First rent due', 'date' => $moveIn->copy()->addMonthsNoOverflow(1)] : null;
        }

        return $this->due_date ? ['label' => 'Due date', 'date' => $this->due_date] : null;
    }

    /** Printed under the upfront payment's breakdown. */
    public function refundNote(): ?string
    {
        return $this->type === 'booking_upfront'
            ? 'The full upfront payment is refunded if the booking is cancelled before the move-in date.'
            : null;
    }

    /** Whether lines() splits this payment into several amounts. */
    public function hasBreakdown(): bool
    {
        return count($this->lines()) > 1;
    }

    /**
     * Line shown to the payer on Xendit's checkout page — shared by the web
     * and mobile pay flows so both invoices read the same.
     */
    public function gatewayDescription(): string
    {
        $room = ($this->booking?->room ?? $this->lease?->room)?->room_number;
        $suffix = $room ? " for Room {$room}" : '';

        return match ($this->type) {
            'booking_upfront' => "3-month upfront payment (advance, deposit, security){$suffix}",
            'rent' => "Monthly rent{$suffix}",
            'utility' => "Utility bill{$suffix}",
            'transfer_adjustment' => "Room transfer deposit adjustment{$suffix}",
            'move_out_balance' => "Move-out balance (damages and unpaid bills){$suffix}",
            default => ucfirst(str_replace('_', ' ', $this->type)).' payment',
        };
    }
}
