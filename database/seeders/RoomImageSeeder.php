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

    /**
     * Gradient pairs cycled per photo slot, matching the line-art style already
     * used by public/images/hero-placeholder.svg so seeded room photos, the
     * hero fallback, and the logo mark all read as one consistent illustration
     * language instead of flat placeholder rectangles.
     */
    protected array $gradients = [
        ['#2d6a4f', '#10291f'],
        ['#40916c', '#1b4332'],
        ['#74c69d', '#2d6a4f'],
        ['#95d5b2', '#40916c'],
    ];

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

                        foreach (range(0, 3) as $order) {
                            $path = "rooms/room-{$room->id}-{$order}.svg";

                            Storage::disk('public')->put($path, $this->placeholderSvg($room->room_number, $order));

                            $room->images()->create([
                                'path' => $path,
                                'sort_order' => $order,
                            ]);
                        }

                        return;
                    }

                    // Every other room: a single placeholder photo so no room shows up
                    // blank in listings, without fabricating size/description/inclusions.
                    $variant = $index % 4;
                    $path = "rooms/room-{$room->id}-0.svg";

                    Storage::disk('public')->put($path, $this->placeholderSvg($room->room_number, $variant));

                    $room->images()->create([
                        'path' => $path,
                        'sort_order' => 0,
                    ]);
                });
            });
    }

    protected function placeholderSvg(string $roomNumber, int $variant): string
    {
        $variant %= 4;
        [$from, $to] = $this->gradients[$variant];
        $scene = $this->sceneMarkup($variant);

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600">
            <defs>
                <linearGradient id="bg{$variant}" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="{$from}"/>
                    <stop offset="100%" stop-color="{$to}"/>
                </linearGradient>
            </defs>
            <rect width="800" height="600" fill="url(#bg{$variant})"/>
            <g stroke="#f6efe2" stroke-width="6" stroke-linecap="round" stroke-linejoin="round" fill="none" opacity="0.85">
                {$scene}
            </g>
            <rect x="24" y="24" width="132" height="34" rx="17" fill="#10291f" fill-opacity="0.35"/>
            <text x="90" y="46" text-anchor="middle" font-family="-apple-system,Segoe UI,Roboto,sans-serif" font-size="16" fill="#f6efe2" font-weight="600">Room {$roomNumber}</text>
        </svg>
        SVG;
    }

    /**
     * Four simple furnished-room scenes drawn in the same line-art style as the
     * hero fallback icon, cycled per photo slot so a room's gallery shows some
     * visual variety instead of four identical tinted rectangles.
     */
    protected function sceneMarkup(int $variant): string
    {
        return match ($variant) {
            0 => // bed + window
                '<rect x="480" y="220" width="140" height="110" rx="10"/>'
                .'<line x1="550" y1="220" x2="550" y2="330"/>'
                .'<line x1="480" y1="275" x2="620" y2="275"/>'
                .'<path d="M180 420 h260 v-40 a20 20 0 0 0 -20 -20 h-220 a20 20 0 0 0 -20 20 Z"/>'
                .'<rect x="200" y="330" width="70" height="50" rx="10"/>'
                .'<line x1="180" y1="420" x2="180" y2="450"/>'
                .'<line x1="440" y1="420" x2="440" y2="450"/>',
            1 => // study desk
                '<line x1="260" y1="380" x2="560" y2="380"/>'
                .'<line x1="280" y1="380" x2="280" y2="440"/>'
                .'<line x1="540" y1="380" x2="540" y2="440"/>'
                .'<rect x="330" y="330" width="120" height="50" rx="6"/>'
                .'<rect x="470" y="200" width="90" height="70" rx="8"/>',
            2 => // sofa + plant
                '<path d="M220 380 h300 v-40 a20 20 0 0 0 -20 -20 h-260 a20 20 0 0 0 -20 20 Z"/>'
                .'<rect x="240" y="300" width="100" height="50" rx="12"/>'
                .'<rect x="380" y="300" width="100" height="50" rx="12"/>'
                .'<ellipse cx="370" cy="430" rx="180" ry="20"/>'
                .'<path d="M560 300 q20 -60 60 -60 q40 0 40 40 q0 40 -40 60"/>'
                .'<line x1="600" y1="300" x2="600" y2="380"/>',
            default => // doorway + shelf
                '<rect x="320" y="180" width="140" height="220" rx="8"/>'
                .'<circle cx="440" cy="290" r="6"/>'
                .'<line x1="520" y1="260" x2="620" y2="260"/>'
                .'<rect x="530" y="220" width="20" height="40" rx="3"/>'
                .'<rect x="560" y="215" width="20" height="45" rx="3"/>'
                .'<circle cx="600" cy="200" r="24"/>'
                .'<line x1="600" y1="200" x2="600" y2="186"/>'
                .'<line x1="600" y1="200" x2="612" y2="200"/>',
        };
    }
}
