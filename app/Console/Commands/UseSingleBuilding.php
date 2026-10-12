<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Property;
use App\Models\Room;
use App\Models\RoomTransfer;
use App\Support\BuildingLayout;
use App\Support\StockRoomPhotos;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The system was first seeded with made-up numbers (53 rooms in two
 * properties). The real place is one building with 14 units. This reshapes an
 * existing database to match — without losing any tenant's records: a room
 * that has bookings, leases or maintenance history is always kept (and moved
 * into the building, renumbered if its number is taken or out of range).
 */
class UseSingleBuilding extends Command
{
    protected $signature = 'app:use-single-building {--dry-run : Show what would change without changing anything}';

    protected $description = 'Reshape the data to the real property: one building with 14 rooms (rooms with tenant records are kept)';

    public function handle(): int
    {
        $keep = Property::orderBy('id')->first();

        if (! $keep) {
            $this->error('There is no property yet — run the seeders instead.');

            return self::FAILURE;
        }

        $slots = BuildingLayout::slots();
        $inUse = $this->roomsWithRecords();

        if ($inUse->count() > count($slots)) {
            $this->error("{$inUse->count()} rooms have tenant records, but the building only has ".count($slots).' rooms. Nothing was changed.');

            return self::FAILURE;
        }

        // slot number => the existing room that will occupy it (null = create a new room).
        $plan = array_fill_keys(array_keys($slots), null);

        // 1. Rooms with records that already sit on a valid number in this building stay put.
        foreach ($inUse as $room) {
            if ((int) $room->property_id === $keep->id && array_key_exists($room->room_number, $plan) && $plan[$room->room_number] === null) {
                $plan[$room->room_number] = $room;
            }
        }

        // 2. The other rooms with records take the next free numbers.
        $placed = collect($plan)->filter()->pluck('id');
        foreach ($inUse->whereNotIn('id', $placed) as $room) {
            $free = collect($plan)->search(fn ($r) => $r === null);
            $plan[$free] = $room;
        }

        // 3. Remaining numbers keep the unused room that already has that number, if any.
        $spare = Room::where('property_id', $keep->id)->whereNotIn('id', $inUse->pluck('id'))->get()->keyBy('room_number');
        foreach ($plan as $number => $room) {
            $plan[$number] ??= $spare->get((string) $number);
        }

        $keptIds = collect($plan)->filter()->pluck('id');
        $toDelete = Room::whereNotIn('id', $keptIds)->with('property')->get();
        $otherProperties = Property::where('id', '!=', $keep->id)->get();

        $this->describe($keep, $plan, $slots, $toDelete, $otherProperties);

        if ($this->option('dry-run')) {
            $this->warn('Dry run — nothing was changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($keep, $plan, $slots, $toDelete, $inUse, $otherProperties) {
            // Never delete a room that has records: deleting a room deletes its bookings and leases with it.
            abort_if($toDelete->pluck('id')->intersect($inUse->pluck('id'))->isNotEmpty(), 500, 'Refusing to delete a room that has tenant records.');

            foreach ($toDelete as $room) {
                $room->images->each->removeWithFile();
                $room->delete();
            }

            Announcement::whereIn('property_id', $otherProperties->pluck('id'))->update(['property_id' => $keep->id]);

            // Two passes, so a room moving onto a number another moving room still holds
            // doesn't trip the unique (property, room number) rule.
            foreach (array_filter($plan) as $room) {
                $room->update(['property_id' => $keep->id, 'room_number' => 'tmp-'.$room->id]);
            }

            $rate = Room::whereIn('id', collect($plan)->filter()->pluck('id'))->value('monthly_rate') ?? BuildingLayout::MONTHLY_RATE;

            foreach ($plan as $number => $room) {
                if ($room) {
                    $room->update(['room_number' => (string) $number, 'floor' => $slots[$number]]);
                } else {
                    $room = Room::create([
                        'property_id' => $keep->id,
                        'room_number' => (string) $number,
                        'floor' => $slots[$number],
                        'type' => 'standard',
                        'monthly_rate' => $rate,
                        'status' => 'vacant',
                    ]);
                }

                StockRoomPhotos::applyTo($room);
            }

            $otherProperties->each->delete();
            $keep->update(['name' => BuildingLayout::PROPERTY_NAME]);
        });

        $this->info('Done: '.Property::count().' property, '.Room::count().' rooms.');

        return self::SUCCESS;
    }

    /** Rooms that carry tenant history: bookings, leases, maintenance requests or transfers. */
    private function roomsWithRecords(): Collection
    {
        $transferRooms = RoomTransfer::query()->get(['from_room_id', 'to_room_id'])
            ->flatMap(fn ($t) => [$t->from_room_id, $t->to_room_id]);

        return Room::where(fn ($q) => $q
            ->whereHas('bookings')
            ->orWhereHas('leases')
            ->orWhereHas('maintenanceRequests')
            ->orWhereIn('id', $transferRooms))
            ->with('property')
            ->orderBy('property_id')
            ->orderBy('room_number')
            ->get();
    }

    private function describe(Property $keep, array $plan, array $slots, Collection $toDelete, Collection $otherProperties): void
    {
        $this->line("Building: \"{$keep->name}\" → \"".BuildingLayout::PROPERTY_NAME.'"');

        foreach ($plan as $number => $room) {
            $label = match (true) {
                $room === null => 'new room',
                (int) $room->property_id === $keep->id && $room->room_number === (string) $number => 'stays',
                default => "moved from {$room->property->name} room {$room->room_number} (has tenant records)",
            };
            $this->line("  Room {$number} (floor {$slots[$number]}): {$label}");
        }

        $this->line("Rooms removed: {$toDelete->count()} (none has any booking, lease or maintenance record)");

        foreach ($otherProperties as $property) {
            $this->line("Property removed: {$property->name}");
        }
    }
}
