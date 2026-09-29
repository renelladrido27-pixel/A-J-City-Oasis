<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    /**
     * Same audience rules as the web tenant page (Announcement::visibleTo) —
     * everyone, the tenant's property or floor, or addressed to them directly.
     *
     * Unlike the web page this doesn't mark announcement alerts read: the app
     * prefetches every section on login, so that would clear them before the
     * tenant ever opened the News filter.
     */
    public function index(Request $request): JsonResponse
    {
        $announcements = Announcement::with(['author', 'property', 'tenant'])
            ->visibleTo($request->user())
            ->latest()
            ->take(50)
            ->get();

        return response()->json(['announcements' => AnnouncementResource::collection($announcements)]);
    }
}
