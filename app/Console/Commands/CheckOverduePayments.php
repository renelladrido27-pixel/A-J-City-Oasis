<?php

namespace App\Console\Commands;

use App\Mail\PaymentOverdueMail;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckOverduePayments extends Command
{
    protected $signature = 'app:check-overdue-payments';

    protected $description = 'Flag pending payments as overdue once the one-month grace period has passed';

    public function handle(NotificationService $notifications): int
    {
        $overdue = Payment::where('status', 'pending')
            ->whereDate('due_date', '<', now()->subDays(30))
            ->with(['lease.tenant', 'booking.tenant'])
            ->get();

        foreach ($overdue as $payment) {
            $payment->update(['status' => 'overdue']);

            $tenant = $payment->lease?->tenant ?? $payment->booking?->tenant;

            if ($tenant) {
                $notifications->notify(
                    $tenant,
                    'Payment overdue',
                    ucfirst($payment->type).' payment of ₱'.number_format($payment->amount, 2).' is now overdue. Please settle it as soon as possible.',
                    'payment',
                );

                Mail::to($tenant->email)->send(new PaymentOverdueMail($payment));
            }
        }

        $this->info("Marked {$overdue->count()} payment(s) overdue.");

        return self::SUCCESS;
    }
}
