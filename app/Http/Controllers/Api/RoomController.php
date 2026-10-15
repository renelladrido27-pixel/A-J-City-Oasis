<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use App\Support\RentalAgreement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rooms = Room::where('status', 'vacant')
            ->when($request->integer('property_id'), fn ($q, $id) => $q->where('property_id', $id))
            // `images` too — without it the app has no photos to show.
            ->with(['property', 'images'])
            // Grouped by building, then floor — the same number can exist in two buildings.
            ->orderBy('property_id')->orderBy('floor')->orderBy('room_number')
            ->get();

        return response()->json(['rooms' => RoomResource::collection($rooms)]);
    }

    /** The Terms and Conditions for booking this room — the same text as the website. */
    public function terms(Room $room): JsonResponse
    {
        $name = auth('sanctum')->user()?->name ?? 'the Tenant';

        return response()->json([
            'title' => RentalAgreement::TITLE,
            'sections' => RentalAgreement::sections($room, $name),
        ]);
    }

    public function show(Room $room): JsonResponse
    {
        $room->load(['property', 'images']);

        return response()->json(['room' => RoomResource::make($room)]);
    }
}
