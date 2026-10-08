<?php

namespace App\Console\Commands;

use App\Mail\PaymentDueSoonMail;
use App\Models\Payment;
use App\Services\MailService;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class SendPaymentReminders extends Command
{
    /** How many days before the due date the tenant is reminded. */
    public const DAYS_BEFORE = 7;

    protected $signature = 'app:send-payment-reminders';

    protected $description = 'Remind tenants of unpaid rent and utility bills one week before they are due';

    public function handle(NotificationService $notifications): int
    {
        // "Due within a week" rather than "due in exactly 7 days", so a day the
        // scheduler missed doesn't mean a missed reminder; reminder_sent_at
        // keeps it to one reminder per bill.
        $dueSoon = Payment::where('status', 'pending')
            ->whereNotNull('lease_id')
            ->whereNull('reminder_sent_at')
            ->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays(self::DAYS_BEFORE))
            ->with(['lease.tenant', 'lease.room'])
            ->get();

        $sent = 0;

        foreach ($dueSoon as $payment) {
            $payment->update(['reminder_sent_at' => now()]);

            $tenant = $payment->lease?->tenant;

            if (! $tenant) {
                continue;
            }

            $notifications->notify(
                $tenant,
                ucfirst(str_replace('_', ' ', $payment->type)).' payment due '.self::when($payment),
                'Your '.str_replace('_', ' ', $payment->type).' payment of ₱'.number_format($payment->amount, 2)
                    .' is due on '.$payment->due_date->format('F j, Y').'. Pay online any time before then.',
                'payment',
            );

            app(MailService::class)->send($tenant->email, new PaymentDueSoonMail($payment));
            $sent++;
        }

        $this->info("Sent {$sent} payment reminder(s).");

        return self::SUCCESS;
    }

    /** "today", "tomorrow" or "in 7 days". */
    public static function when(Payment $payment): string
    {
        $days = (int) today()->diffInDays($payment->due_date->copy()->startOfDay());

        return match (true) {
            $days <= 0 => 'today',
            $days === 1 => 'tomorrow',
            default => "in {$days} days",
        };
    }
}
