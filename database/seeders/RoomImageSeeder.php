<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Room;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class RoomImageSeeder extends Seeder
{
    /**
     * Demo detail sets applied to a few showcase rooms per property so the
     * room-details page and listing cards have realistic sample content.
     */
    protected array $sampleDetails = [
        [
            'size_sqm' => 16,
            'description' => 'Cozy room near the entrance with plenty of natural light.',
            'inclusions' => ['2 Beds', 'Own Sink & CR', 'Free Wi-Fi'],
        ],
        [
            'size_sqm' => 20,
            'description' => 'Spacious corner room, quiet side of the building.',
            'inclusions' => ['1 Queen Bed', 'Own CR', 'Aircon', 'Study Table'],
        ],
        [
            'size_sqm' => 18,
            'description' => 'Bright room with a window view, close to the common area.',
            'inclusions' => ['2 Beds', 'Own Sink', 'Shared CR', 'Cable TV'],
        ],
    ];

    protected array $photoColors = ['#1b4332', '#2d6a4f', '#40916c', '#74c69d'];

    public function run(): void
    {
        Property::with(['rooms' => fn ($q) => $q->orderBy('room_number')])
            ->get()
            ->each(function (Property $property) {
                $property->rooms->each(function (Room $room, int $index) {
                    // Idempotent: never touch a room that already has photos (showcase rooms
                    // from a prior run, or rooms an admin has already uploaded real photos to).
                    if ($room->images()->exists()) {
                        return;
                    }

                    if ($index < 3) {
                        $details = $this->sampleDetails[$index % count($this->sampleDetails)];
                        $room->update($details);

                        foreach ($this->photoColors as $order => $color) {
                            $path = "rooms/room-{$room->id}-{$order}.svg";

                            Storage::disk('public')->put($path, $this->placeholderSvg($room->room_number, $order + 1, $color));

                            $room->images()->create([
                                'path' => $path,
                                'sort_order' => $order,
                            ]);
                        }

                        return;
                    }

                    // Every other room: a single placeholder photo so no room shows up
                    // blank in listings, without fabricating size/description/inclusions.
                    $color = $this->photoColors[$index % count($this->photoColors)];
                    $path = "rooms/room-{$room->id}-0.svg";

                    Storage::disk('public')->put($path, $this->placeholderSvg($room->room_number, 1, $color));

                    $room->images()->create([
                        'path' => $path,
                        'sort_order' => 0,
                    ]);
                });
            });
    }

    protected function placeholderSvg(string $roomNumber, int $photoNumber, string $color): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="800" height="600">
            <rect width="800" height="600" fill="{$color}" />
            <text x="400" y="290" font-family="Arial, sans-serif" font-size="40" fill="#ffffff" text-anchor="middle">Room {$roomNumber}</text>
            <text x="400" y="340" font-family="Arial, sans-serif" font-size="24" fill="#ffffff" text-anchor="middle" opacity="0.8">Photo {$photoNumber}</text>
        </svg>
        SVG;
    }
}
