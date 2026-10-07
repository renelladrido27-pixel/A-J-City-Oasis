<?php

namespace App\Support;

/**
 * Floors are stored as numbers (1, 2, 3…) but shown in words — "First Floor",
 * "Second Floor" — as requested by the panel.
 */
class Floor
{
    private const ORDINALS = [
        1 => 'First', 2 => 'Second', 3 => 'Third', 4 => 'Fourth', 5 => 'Fifth',
        6 => 'Sixth', 7 => 'Seventh', 8 => 'Eighth', 9 => 'Ninth', 10 => 'Tenth',
        11 => 'Eleventh', 12 => 'Twelfth', 13 => 'Thirteenth', 14 => 'Fourteenth', 15 => 'Fifteenth',
        16 => 'Sixteenth', 17 => 'Seventeenth', 18 => 'Eighteenth', 19 => 'Nineteenth', 20 => 'Twentieth',
    ];

    public static function label(int|string|null $floor): string
    {
        if ($floor === null || $floor === '') {
            return '—';
        }

        $floor = (int) $floor;

        return (self::ORDINALS[$floor] ?? self::numericOrdinal($floor)).' Floor';
    }

    /**
     * Options for a floor <select>: [1 => 'First Floor', …].
     *
     * @return array<int, string>
     */
    public static function options(int $max = 10): array
    {
        $options = [];
        for ($i = 1; $i <= $max; $i++) {
            $options[$i] = self::label($i);
        }

        return $options;
    }

    private static function numericOrdinal(int $n): string
    {
        $suffix = ($n % 100 >= 11 && $n % 100 <= 13) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th');

        return $n.$suffix;
    }
}
