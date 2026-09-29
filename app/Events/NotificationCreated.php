<?php

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushed to the recipient's private channel the moment a notification is
 * stored, so an open page can bump its bell badge and show a toast without
 * a reload. Broadcast synchronously (ShouldBroadcastNow) because nothing
 * runs a queue worker in this project's dev or demo setup.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public Notification $notification) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('users.'.$this->notification->user_id);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'type' => $this->notification->type,
            // Relative: the trigger may come from another host (mobile API over the
            // LAN IP, a queue, tinker) than the one the recipient's browser is on.
            'url' => route('notifications.open', $this->notification, absolute: false),
            'unread_count' => $this->notification->user->notifications()->whereNull('read_at')->count(),
        ];
    }
}
