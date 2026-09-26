<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function notify(User $user, string $title, string $message, string $type = 'general'): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
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
