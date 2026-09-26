<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaseResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaseController extends Controller
{
    /**
     * The tenant's current lease for the mobile "My Rental" screen. A tenant
     * has at most one active lease at a time in this system, so unlike the web
     * app's paginated leases.index this just returns the single active one
     * (or the most recent lease if none is active, e.g. after move-out).
     */
    public function show(Request $request): JsonResponse
    {
        $lease = $request->user()->leases()
            ->with('room.property')
            ->where('status', 'active')
            ->latest('start_date')
            ->first()
            ?? $request->user()->leases()->with('room.property')->latest('start_date')->first();

        return response()->json(['lease' => $lease ? LeaseResource::make($lease) : null]);
    }
}
