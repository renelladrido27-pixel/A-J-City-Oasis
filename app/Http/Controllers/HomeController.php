<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use App\Models\User;
use App\Support\StockRoomPhotos;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route(match (auth()->user()->role) {
                'admin' => 'admin.dashboard',
                'staff' => 'staff.maintenance.index',
                default => 'tenant.dashboard',
            });
        }

        $stats = [
            'rooms' => Room::count(),
            'properties' => Property::count(),
            'tenants' => User::where('role', 'tenant')->count(),
        ];

        // Vacant rooms that have real photos come first, so the home page shows
        // actual pictures of a unit rather than placeholder drawings.
        $photographed = StockRoomPhotos::photographedRoomIds();
        $featuredRooms = Room::where('status', 'vacant')->with(['property', 'images'])->orderBy('id')->get()
            ->sortBy(fn (Room $room) => in_array((int) $room->id, $photographed, true) ? 0 : 1)
            ->take(3)
            ->values();

        return view('home', compact('stats', 'featuredRooms'));
    }
}
