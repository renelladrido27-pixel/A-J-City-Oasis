<?php

namespace App\Support;

use App\Models\Property;
use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The owner's real photos of a unit and of the building
 * (database/seeders/room-photos/, numbered in display order).
 *
 * They are shown on the twelve rooms that were photographed — the first six
 * rooms on each of the first two floors of the first building — and nowhere
 * else: every other room keeps a drawn placeholder (PlaceholderRoomArt) until
 * the admin uploads photos of it. The twelve rooms point at the same files on
 * the public disk (rooms/stock/…) instead of each getting a copy.
 */
class StockRoomPhotos
{
    public const DIR = 'rooms/stock';

    public const FLOORS = [1, 2];

    public const ROOMS_PER_FLOOR = 6;

    /** What the photos show — set on the photographed rooms that have no details of their own. */
    public const DESCRIPTION = 'Studio unit with a loft bed, its own kitchenette and a private bathroom.';

    public const INCLUSIONS = ['Loft Bed', 'Kitchenette with Sink', 'Own CR with Shower', 'Built-in Cabinets', 'Aircon'];

    /** @return list<string> absolute source files, in display order */
    public static function sourceFiles(): array
    {
        $files = File::glob(database_path('seeders/room-photos/*.jpg'));
        sort($files);

        return $files;
    }

    /**
     * Copies the photos to the public disk (skipping ones already there).
     *
     * @return list<string> their paths on the public disk, in display order
     */
    public static function publish(): array
    {
        $disk = Storage::disk('public');

        return array_map(function (string $file) use ($disk) {
            $path = self::DIR.'/'.basename($file);

            if (! $disk->exists($path) || $disk->size($path) !== filesize($file)) {
                $disk->put($path, File::get($file));
            }

            return $path;
        }, self::sourceFiles());
    }

    /** @return list<string> URLs of the published photos (empty until publish() has run) */
    public static function urls(): array
    {
        $disk = Storage::disk('public');

        return collect(self::sourceFiles())
            ->map(fn (string $file) => self::DIR.'/'.basename($file))
            ->filter(fn (string $path) => $disk->exists($path))
            // asset(), not Storage::url(): built from the request's host (see RoomImage::url()).
            ->map(fn (string $path) => asset('storage/'.$path))
            ->values()
            ->all();
    }

    /**
     * The rooms that show the real photos: the first six (by room number) on
     * floors 1 and 2 of the first property.
     *
     * @return list<int> room ids
     */
    public static function photographedRoomIds(): array
    {
        $property = Property::orderBy('id')->first();

        if (! $property) {
            return [];
        }

        return collect(self::FLOORS)
            ->flatMap(fn (int $floor) => Room::where('property_id', $property->id)
                ->where('floor', $floor)
                ->orderBy('room_number')
                ->limit(self::ROOMS_PER_FLOOR)
                ->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function isStock(RoomImage $image): bool
    {
        return str_starts_with($image->path, self::DIR.'/');
    }

    /** One of the generated line-art drawings. */
    public static function isPlaceholder(RoomImage $image): bool
    {
        return (bool) preg_match('#^rooms/room-\d+-\d+\.svg$#', $image->path);
    }

    /**
     * Makes a room show what it should: the real photos if it is one of the
     * photographed rooms, a drawing otherwise. A room with photos of its own
     * (anything an admin uploaded) is never touched.
     *
     * @return bool whether the room was changed
     */
    public static function sync(Room $room, bool $photographed): bool
    {
        $images = $room->images()->orderBy('sort_order')->get();

        if ($images->contains(fn (RoomImage $image) => ! self::isStock($image) && ! self::isPlaceholder($image))) {
            return false;
        }

        $wanted = $photographed ? self::publish() : ["rooms/room-{$room->id}-0.svg"];

        if ($images->pluck('path')->all() === $wanted) {
            return false;
        }

        $images->each->removeWithFile();

        if (! $photographed) {
            Storage::disk('public')->put($wanted[0], PlaceholderRoomArt::svg($room->room_number, $room->id % 4));
        }

        foreach ($wanted as $order => $path) {
            $room->images()->create(['path' => $path, 'sort_order' => $order]);
        }

        return true;
    }

    /** Applies sync() to every room, and gives the photographed rooms matching details. */
    public static function syncAll(): array
    {
        $photographed = self::photographedRoomIds();
        $counts = ['photos' => 0, 'drawings' => 0, 'own' => 0];

        Room::with('images')->orderBy('id')->each(function (Room $room) use ($photographed, &$counts) {
            $isPhotographed = in_array((int) $room->id, $photographed, true);
            self::sync($room, $isPhotographed);

            $shows = $room->images()->get();
            $counts[match (true) {
                $shows->contains(fn (RoomImage $i) => ! self::isStock($i) && ! self::isPlaceholder($i)) => 'own',
                $isPhotographed => 'photos',
                default => 'drawings',
            }]++;

            if ($isPhotographed && blank($room->description)) {
                $room->update(['description' => self::DESCRIPTION, 'inclusions' => self::INCLUSIONS]);
            }
        });

        return $counts;
    }
}
