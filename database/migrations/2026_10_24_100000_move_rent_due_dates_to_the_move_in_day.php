<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rent used to fall due on the 1st of the month. It now falls on the move-in
 * day each month (moved in Oct 27 → due Nov 27). Every existing rent bill —
 * paid or not, so nobody is billed twice — moves to its lease's day in the
 * same month. An unpaid bill whose date moved gets its 7-day reminder again,
 * and stops counting as overdue if the new date hasn't passed its grace period.
 */
return new class extends Migration
{
    public function up(): void
    {
        $leases = DB::table('leases')->whereNotNull('start_date')->pluck('start_date', 'id');

        DB::table('payments')->where('type', 'rent')->whereNotNull('lease_id')->orderBy('id')
            ->each(function ($payment) use ($leases) {
                $start = $leases[$payment->lease_id] ?? null;
                if (! $start || ! $payment->due_date) {
                    return;
                }

                $start = Carbon::parse($start)->startOfDay();
                $due = Carbon::parse($payment->due_date)->startOfDay();
                $months = ($due->year - $start->year) * 12 + $due->month - $start->month;
                if ($months < 1) {
                    return;
                }

                $moved = $start->copy()->addMonthsNoOverflow($months);
                if ($moved->equalTo($due)) {
                    return;
                }

                $changes = ['due_date' => $moved->toDateString()];
                if (in_array($payment->status, ['pending', 'overdue'], true)) {
                    $changes['reminder_sent_at'] = null;
                    if ($payment->status === 'overdue' && $moved->copy()->addMonth()->isAfter(today())) {
                        $changes['status'] = 'pending';
                    }
                }

                DB::table('payments')->where('id', $payment->id)->update($changes);
            });
    }

    public function down(): void
    {
        // Moving bills back to the 1st would lose nothing but the day; not needed.
    }
};
