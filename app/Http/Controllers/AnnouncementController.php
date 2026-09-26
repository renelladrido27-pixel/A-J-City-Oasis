<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

    public function index(Request $request): View
    {
        $announcements = Announcement::with(['author', 'property'])
            ->visibleTo($request->user())
            ->latest()
            ->paginate(20);

        $this->notifications->markTypesRead($request->user(), ['announcement']);

        return view('announcements.index', compact('announcements'));
    }
}
