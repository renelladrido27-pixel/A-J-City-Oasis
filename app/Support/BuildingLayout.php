<?php

namespace App\Support;

/**
 * The real property: one two-storey building with 14 studio units, seven to
 * a floor (101–107 and 201–207). Used by the seeders for a fresh database and
 * by `app:use-single-building` to reshape an existing one.
 */
class BuildingLayout
{
    public const PROPERTY_NAME = 'A&J CITY OASIS';

    public const ADDRESS = 'Koronadal City, South Cotabato';

    public const FLOORS = 2;

    public const ROOMS_PER_FLOOR = 7;

    public const MONTHLY_RATE = 3500;

    /** @return array<int, int> room number => floor, in order */
    public static function slots(): array
    {
        $slots = [];

        foreach (range(1, self::FLOORS) as $floor) {
            foreach (range(1, self::ROOMS_PER_FLOOR) as $n) {
                $slots[$floor * 100 + $n] = $floor;
            }
        }

        return $slots;
    }

    public static function roomCount(): int
    {
        return self::FLOORS * self::ROOMS_PER_FLOOR;
    }
}
