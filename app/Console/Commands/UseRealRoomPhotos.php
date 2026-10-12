<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Support\StockRoomPhotos;
use Illuminate\Console\Command;

class UseRealRoomPhotos extends Command
{
    protected $signature = 'app:use-real-room-photos';

    protected $description = "Replace the placeholder room drawings with the owner's real photos (rooms with their own uploaded photos are left alone)";

    /** Descriptions the old seeder invented for its showcase rooms. */
    private const MADE_UP_DESCRIPTIONS = [
        'Cozy room near the entrance with plenty of natural light.',
        'Spacious corner room, quiet side of the building.',
        'Bright room with a window view, close to the common area.',
    ];

    public function handle(): int
    {
        if (StockRoomPhotos::sourceFiles() === []) {
            $this->error('No photos found in database/seeders/room-photos/.');

            return self::FAILURE;
        }

        $updated = 0;
        $kept = 0;

        Room::with('images')->each(function (Room $room) use (&$updated, &$kept) {
            StockRoomPhotos::applyTo($room) ? $updated++ : $kept++;

            // The made-up sample details ("Shared CR", "Cable TV"…) would now
            // contradict the photos — replace them with what the photos show.
            if (in_array($room->description, self::MADE_UP_DESCRIPTIONS, true)) {
                $room->update([
                    'description' => StockRoomPhotos::DESCRIPTION,
                    'inclusions' => StockRoomPhotos::INCLUSIONS,
                ]);
            }
        });

        $this->info("Real photos set on {$updated} room(s); {$kept} room(s) already had their own photos.");

        return self::SUCCESS;
    }
}
