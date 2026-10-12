<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Room;
use App\Support\StockRoomPhotos;
use Illuminate\Database\Seeder;

class RoomImageSeeder extends Seeder
{
    /**
     * Gives every room without photos the owner's real photos of a typical
     * unit (see StockRoomPhotos), and a few showcase rooms per property a
     * description and inclusions list matching those photos, so the listing
     * cards and the room-details page have real content.
     *
     * Safe to re-run: rooms that already have photos are left alone.
     */
    public function run(): void
    {
        Property::with(['rooms' => fn ($q) => $q->orderBy('room_number')])
            ->get()
            ->each(function (Property $property) {
                $property->rooms->each(function (Room $room, int $index) {
                    if ($room->images()->exists()) {
                        return;
                    }

                    StockRoomPhotos::applyTo($room);

                    if ($index < 3) {
                        $room->update([
                            'size_sqm' => [16, 20, 18][$index],
                            'description' => StockRoomPhotos::DESCRIPTION,
                            'inclusions' => StockRoomPhotos::INCLUSIONS,
                        ]);
                    }
                });
            });
    }
}
