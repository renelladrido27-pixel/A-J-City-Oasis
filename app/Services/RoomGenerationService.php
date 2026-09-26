<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Room;

class RoomGenerationService
{
    /**
     * Bulk-create rooms for a property, numbered by floor (e.g. 101, 102, ... 201, 202, ...).
     * Skips room numbers that already exist on the property.
     */
    public function generate(Property $property, int $count, float $monthlyRate, string $type = 'standard', int $roomsPerFloor = 10): int
    {
        $existingNumbers = $property->rooms()->pluck('room_number')->all();
        $existingCount = count($existingNumbers);
        $created = 0;

        for ($i = $existingCount + 1; $i <= $existingCount + $count; $i++) {
            $floor = (int) ceil($i / $roomsPerFloor);
            $roomOnFloor = $i - (($floor - 1) * $roomsPerFloor);
            $roomNumber = sprintf('%d%02d', $floor, $roomOnFloor);

            if (in_array($roomNumber, $existingNumbers, true)) {
                continue;
            }

            Room::create([
                'property_id' => $property->id,
                'room_number' => $roomNumber,
                'floor' => $floor,
                'type' => $type,
                'monthly_rate' => $monthlyRate,
                'status' => 'vacant',
            ]);

            $created++;
        }

        return $created;
    }
}
