<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Support\StockRoomPhotos;
use Illuminate\Console\Command;

class UseRealRoomPhotos extends Command
{
    protected $signature = 'app:use-real-room-photos';

    protected $description = "Show the owner's real photos on the 12 photographed rooms (first six on floors 1 and 2 of the first building) and a drawing on the rest; rooms with their own uploaded photos are left alone";

    /** Descriptions the first seeder invented for its showcase rooms. */
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

        // The made-up sample details ("Shared CR", "Cable TV"…) would contradict the
        // photos — clear them, so the photographed rooms get what the photos show.
        Room::whereIn('description', self::MADE_UP_DESCRIPTIONS)
            ->whereIn('id', StockRoomPhotos::photographedRoomIds())
            ->update(['description' => null, 'inclusions' => null]);

        $counts = StockRoomPhotos::syncAll();

        $this->info("Real photos on {$counts['photos']} room(s), a drawing on {$counts['drawings']}, {$counts['own']} with their own uploaded photos left as they are.");

        return self::SUCCESS;
    }
}
