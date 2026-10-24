<?php

namespace App\Console\Commands;

use App\Models\Room;
use Illuminate\Console\Command;

/**
 * Makes each room's status match reality: a room with an active lease is
 * occupied, one held by an unfinished booking is reserved, and one marked
 * occupied/reserved with neither is vacant again. Rooms under maintenance are
 * left alone. Runs daily as a safety net, and can be run by hand.
 */
class SyncRoomStatus extends Command
{
    protected $signature = 'app:sync-room-status';

    protected $description = 'Set every room to occupied, reserved or vacant according to its leases and bookings';

    public function handle(): int
    {
        $fixed = 0;

        Room::with(['property', 'activeLease', 'reservingBooking'])->each(function (Room $room) use (&$fixed) {
            $should = match (true) {
                $room->activeLease !== null => 'occupied',
                $room->reservingBooking !== null => 'reserved',
                $room->status === 'maintenance' => 'maintenance',
                default => 'vacant',
            };

            if ($room->status !== $should) {
                $this->line("Room {$room->room_number} ({$room->property?->name}): {$room->status} -> {$should}");
                $room->update(['status' => $should]);
                $fixed++;
            }
        });

        $this->info("Fixed {$fixed} room(s).");

        return self::SUCCESS;
    }
}
