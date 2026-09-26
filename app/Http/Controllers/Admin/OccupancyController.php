<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\View\View;

class OccupancyController extends Controller
{
    public function index(): View
    {
        $properties = Property::with(['rooms' => function ($query) {
            $query->orderBy('floor')->orderBy('room_number')
                ->with(['leases' => function ($q) {
                    $q->where('status', 'active')->with(['tenant', 'payments' => function ($p) {
                        $p->where('status', 'overdue');
                    }]);
                }]);
        }])->orderBy('name')->get();

        $allRooms = $properties->flatMap->rooms;

        $summary = [
            'occupied' => $allRooms->where('status', 'occupied')->count(),
            'reserved' => $allRooms->where('status', 'reserved')->count(),
            'vacant' => $allRooms->where('status', 'vacant')->count(),
            'maintenance' => $allRooms->where('status', 'maintenance')->count(),
        ];

        $totalRooms = $allRooms->count();
        $totalProperties = $properties->count();

        return view('admin.occupancy.index', compact('properties', 'summary', 'totalRooms', 'totalProperties'));
    }
}
