<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /** Both buildings (same address), from the owner's listing: Maglantay Subd., Bo. 2 (Barangay Santo Niño), Koronadal City. */
    public const ADDRESS = [
        'region' => 'Region XII (SOCCSKSARGEN)',
        'province' => 'South Cotabato',
        'city' => 'City of Koronadal',
        'barangay' => 'Santo Niño',
        'street' => 'Maglantay Subd., Bo. 2',
        'address' => 'Maglantay Subd., Bo. 2, Santo Niño, City of Koronadal, South Cotabato',
    ];

    public function run(): void
    {
        Property::create([
            'name' => 'A&J CITY OASIS - Main Building',
            ...self::ADDRESS,
        ]);

        Property::create([
            'name' => 'A&J CITY OASIS - Annex Building',
            ...self::ADDRESS,
        ]);
    }
}
