<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $categories = Notification::categories();
        $category = $request->query('category');

        $query = $request->user()->notifications()->latest();

        if ($category && isset($categories[$category])) {
            $query->whereIn('type', $categories[$category]['types']);
        } else {
            $category = null;
        }

        $notifications = $query->paginate(25)->withQueryString();

        return view('notifications.index', compact('notifications', 'categories', 'category'));
    }

    public function markRead(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['read_at' => now()]);

        return back();
    }

    public function markUnread(Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        $notification->update(['read_at' => null]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    /**
     * Marks the notification read, then sends the user to the relevant section
     * for it (or back to the notifications list if it has no specific destination).
     */
    public function open(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === auth()->id(), 403);

        if (! $notification->isRead()) {
            $notification->update(['read_at' => now()]);
        }

        return redirect($notification->linkFor($request->user()) ?? route('notifications.index'));
    }
}
