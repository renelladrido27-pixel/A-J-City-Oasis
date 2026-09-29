<?php

namespace App\Services;

use App\Mail\PaymentReceiptMail;
use App\Models\Payment;

/**
 * Applies the side effects of a payment being marked PAID — shared by the
 * real Xendit webhook and the fake-payment test-mode completion route.
 */
class PaymentCompletionService
{
    public function __construct(
        protected BookingService $bookings,
        protected NotificationService $notifications,
    ) {}

    public function complete(Payment $payment, ?string $xenditPaymentId = null): void
    {
        if ($payment->status === 'paid') {
            return;
        }

        if ($payment->type === 'booking_upfront') {
            $this->bookings->markBookingPaid($payment, $xenditPaymentId);

            return;
        }

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
            'xendit_payment_id' => $xenditPaymentId,
        ]);

        $tenant = $payment->lease?->tenant;

        if ($payment->type === 'utility') {
            $payment->utilityBill?->update(['status' => 'paid']);

            if ($tenant) {
                $this->notifications->notify(
                    $tenant,
                    'Utility payment received',
                    'Your utility payment of ₱'.number_format($payment->amount, 2).' was received.',
                    'payment',
                );
            }
        }

        if ($payment->type === 'transfer_adjustment') {
            $payment->lease?->roomTransfers()
                ->where('status', 'pending')
                ->latest()
                ->first()
                ?->update(['status' => 'approved']);

            if ($tenant) {
                $this->notifications->notify(
                    $tenant,
                    'Transfer deposit paid',
                    'Your room transfer deposit difference was received. An admin will finalize the transfer.',
                    'transfer',
                );
            }
        }

        if ($payment->type === 'rent' && $tenant) {
            $this->notifications->notify(
                $tenant,
                'Rent payment received',
                'Your rent payment of ₱'.number_format($payment->amount, 2).' was received.',
                'payment',
            );
        }

        if ($tenant) {
            app(MailService::class)->send($tenant->email, new PaymentReceiptMail($payment->fresh(['lease'])));
        }
    }
}
