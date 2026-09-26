<?php

namespace Database\Seeders;

use App\Models\Property;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        Property::create([
            'name' => 'A & J OASIS - Main Building',
            'address' => 'Koronadal City, South Cotabato',
        ]);

        Property::create([
            'name' => 'A & J OASIS - Annex Building',
            'address' => 'Koronadal City, South Cotabato',
        ]);
    }
}
