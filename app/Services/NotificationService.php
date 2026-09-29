<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function notify(User $user, string $title, string $message, string $type = 'general'): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);

        // Wait for any surrounding transaction (e.g. markBookingPaid) to commit so
        // the browser never hears about a notification that got rolled back, and
        // never let a Pusher outage fail the booking/payment that triggered it —
        // real-time is a nicety, the stored notification is the source of truth.
        // event(), not broadcast(): broadcast() returns a PendingBroadcast that only
        // sends in its destructor — after rescue() has returned — so a Pusher error
        // would escape the guard. event() sends synchronously inside it.
        DB::afterCommit(fn () => rescue(
            fn () => event(new NotificationCreated($notification)),
            report: true,
        ));

        return $notification;
    }

    public function notifyAdmins(string $title, string $message, string $type = 'general'): void
    {
        User::where('role', 'admin')->each(
            fn (User $admin) => $this->notify($admin, $title, $message, $type)
        );
    }

    public function notifyAdminsAndStaff(string $title, string $message, string $type = 'general'): void
    {
        User::whereIn('role', ['admin', 'staff'])->each(
            fn (User $user) => $this->notify($user, $title, $message, $type)
        );
    }

    /**
     * Marks a user's unread notifications of the given types as read — used
     * to clear a nav section's unread indicator as soon as the user visits
     * that section, rather than requiring each notification to be opened
     * individually first.
     */
    public function markTypesRead(User $user, array $types): void
    {
        $user->notifications()->whereNull('read_at')->whereIn('type', $types)->update(['read_at' => now()]);
    }
}
