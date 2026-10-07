<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoomResource;
use App\Models\Room;
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
            ->orderBy('room_number')
            ->get();

        return response()->json(['rooms' => RoomResource::collection($rooms)]);
    }

    public function show(Room $room): JsonResponse
    {
        $room->load(['property', 'images']);

        return response()->json(['room' => RoomResource::make($room)]);
    }
}
