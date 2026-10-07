<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        Property::create([
            'name' => 'A&J CITY OASIS - Main Building',
            'address' => 'Koronadal City, South Cotabato',
        ]);

        Property::create([
            'name' => 'A&J CITY OASIS - Annex Building',
            'address' => 'Koronadal City, South Cotabato',
        ]);
    }
}
