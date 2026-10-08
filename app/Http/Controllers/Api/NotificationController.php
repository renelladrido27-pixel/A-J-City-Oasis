<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * ?category= one of Notification::categories() keys (booking, lease,
     * payment, maintenance, announcement) — the mobile Alerts screen only
     * surfaces "All" / "Pay" / "Maint." per the wireframe, mapping to
     * category=payment / category=maintenance.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Notification::where('user_id', $request->user()->id)->latest();

        if ($category = $request->string('category')->toString()) {
            $types = Notification::categories()[$category]['types'] ?? [$category];
            $query->whereIn('type', $types);
        }

        return response()->json(['notifications' => NotificationResource::collection($query->get())]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read_at' => $notification->read_at ?? now()]);

        return response()->json(['notification' => new NotificationResource($notification)]);
    }

    public function markUnread(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->update(['read_at' => null]);

        return response()->json(['notification' => new NotificationResource($notification)]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(null, 204);
    }
}
