<?php

namespace App\Services;

use App\Mail\BookingConfirmedMail;
use App\Mail\LeaseStartedMail;
use App\Models\Booking;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BookingService
{
    public function __construct(
        protected XenditService $xendit,
        protected NotificationService $notifications,
    ) {}

    /**
     * Create a booking with the required 3-month upfront payment
     * (advance + deposit + security) and a Xendit invoice for the total.
     */
    public function createBooking(User $tenant, Room $room, ?string $moveInDate = null): Booking
    {
        $monthly = (float) $room->monthly_rate;
        $bookedAt = now();
        $deadline = $bookedAt->copy()->addDays(7);

        return DB::transaction(function () use ($tenant, $room, $moveInDate, $monthly, $bookedAt, $deadline) {
            $locked = Room::where('id', $room->id)->lockForUpdate()->first();

            abort_unless($locked->isVacant(), 422, 'This room is no longer available.');

            $booking = Booking::create([
                'user_id' => $tenant->id,
                'room_id' => $room->id,
                'booked_at' => $bookedAt,
                'move_in_deadline' => $deadline,
                'move_in_date' => $moveInDate,
                'status' => 'pending_payment',
                'advance_amount' => $monthly,
                'deposit_amount' => $monthly,
                'security_amount' => $monthly,
                'total_amount' => $monthly * 3,
                'agreement_accepted_at' => now(),
            ]);

            $locked->update(['status' => 'reserved']);

            $invoice = $this->xendit->createInvoice(
                externalId: 'booking-'.$booking->id,
                amount: (float) $booking->total_amount,
                description: "3-month upfront payment for Room {$room->room_number}",
                payerEmail: $tenant->email,
            );

            Payment::create([
                'booking_id' => $booking->id,
                'type' => 'booking_upfront',
                'amount' => $booking->total_amount,
                'due_date' => $deadline,
                'status' => 'pending',
                'xendit_invoice_id' => $invoice['invoice_id'],
            ]);

            $this->notifications->notify(
                $tenant,
                'Booking created',
                "Your booking for Room {$room->room_number} is pending payment. Complete the ₱".number_format($booking->total_amount, 2).' upfront payment to confirm it.',
                'booking',
            );

            $this->notifications->notifyAdmins(
                'New booking',
                "{$tenant->name} booked Room {$room->room_number} and is pending payment.",
                'booking',
            );

            return $booking;
        });
    }

    public function setMoveInDate(Booking $booking, string $date): Booking
    {
        $date = Carbon::parse($date);

        abort_if($date->gt($booking->move_in_deadline), 422, 'Move-in date must be within 7 days of booking.');

        $booking->update(['move_in_date' => $date]);

        if ($booking->status === 'confirmed') {
            $this->createLeaseForBooking($booking->fresh());
        }

        return $booking->fresh();
    }

    /**
     * Called from the Xendit webhook when the booking_upfront payment is PAID.
     */
    public function markBookingPaid(Payment $payment, ?string $xenditPaymentId = null): void
    {
        DB::transaction(function () use ($payment, $xenditPaymentId) {
            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'xendit_payment_id' => $xenditPaymentId,
            ]);

            $booking = $payment->booking;
            $booking->update(['status' => 'confirmed']);

            $this->notifications->notify(
                $booking->tenant,
                'Payment received',
                "Your upfront payment for Room {$booking->room->room_number} was received. Your booking is now confirmed.",
                'payment',
            );

            Mail::to($booking->tenant->email)->send(new BookingConfirmedMail($booking->fresh(['tenant', 'room.property'])));

            if ($booking->move_in_date) {
                $this->createLeaseForBooking($booking->fresh());
            }
        });
    }

    public function createLeaseForBooking(Booking $booking): Lease
    {
        if ($booking->lease()->exists()) {
            return $booking->lease;
        }

        return DB::transaction(function () use ($booking) {
            $lease = Lease::create([
                'booking_id' => $booking->id,
                'tenant_id' => $booking->user_id,
                'room_id' => $booking->room_id,
                'start_date' => $booking->move_in_date,
                'status' => 'active',
            ]);

            $booking->room->update(['status' => 'occupied']);

            $this->notifications->notify(
                $booking->tenant,
                'Lease started',
                "Your lease for Room {$booking->room->room_number} starts on ".$booking->move_in_date->format('M d, Y').'. Welcome to A & J OASIS!',
                'lease',
            );

            Mail::to($booking->tenant->email)->send(new LeaseStartedMail($lease->fresh(['tenant', 'room.property'])));

            return $lease;
        });
    }

    /**
     * Full refund only if cancelled before the move-in date.
     */
    public function cancelBooking(Booking $booking): void
    {
        abort_unless($booking->isCancellableWithFullRefund(), 422, 'This booking can no longer be cancelled for a full refund.');

        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'cancelled']);
            $booking->payments()->where('status', 'paid')->update(['status' => 'refunded']);
            $booking->room->update(['status' => 'vacant']);

            $this->notifications->notify(
                $booking->tenant,
                'Booking cancelled',
                "Your booking for Room {$booking->room->room_number} was cancelled and fully refunded.",
                'booking',
            );
        });
    }

    /**
     * Cancel bookings that missed the 7-day move-in-date selection window
     * without ever being paid.
     */
    public function expireStaleBookings(): int
    {
        $stale = Booking::where('status', 'pending_payment')
            ->whereDate('move_in_deadline', '<', now())
            ->get();

        foreach ($stale as $booking) {
            $booking->update(['status' => 'expired']);
            $booking->room->update(['status' => 'vacant']);

            $this->notifications->notify(
                $booking->tenant,
                'Booking expired',
                "Your booking for Room {$booking->room->room_number} expired because it wasn't paid within 7 days.",
                'booking',
            );
        }

        return $stale->count();
    }
}
