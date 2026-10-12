<?php

namespace App\Support;

use App\Models\Room;
use App\Models\RoomImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * The owner's real photos of a typical unit and of the building
 * (database/seeders/room-photos/, numbered in display order). Every unit is
 * built the same, so the one set stands in for any room that has no photos of
 * its own yet. All rooms point at the same files on the public disk
 * (rooms/stock/…) instead of each getting a copy.
 */
class StockRoomPhotos
{
    public const DIR = 'rooms/stock';

    /** What the photos show — used for rooms that still carry made-up sample details. */
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

    /** One of the generated line-art drawings the site used before real photos existed. */
    public static function isPlaceholder(RoomImage $image): bool
    {
        return (bool) preg_match('#^rooms/room-\d+-\d+\.svg$#', $image->path);
    }

    /**
     * Gives a room the stock photos — unless it already has photos of its own
     * (anything an admin uploaded). Placeholder drawings are replaced.
     *
     * @return bool whether the room was changed
     */
    public static function applyTo(Room $room): bool
    {
        $images = $room->images()->get();

        if ($images->contains(fn (RoomImage $image) => ! self::isPlaceholder($image))) {
            return false;
        }

        $images->each->removeWithFile();

        foreach (self::publish() as $order => $path) {
            $room->images()->create(['path' => $path, 'sort_order' => $order]);
        }

        return true;
    }
}
