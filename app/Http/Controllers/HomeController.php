<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\Room;
use App\Models\User;
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

        $featuredRooms = Room::where('status', 'vacant')->with(['property', 'images'])->latest()->take(3)->get();

        return view('home', compact('stats', 'featuredRooms'));
    }
}
