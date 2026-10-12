<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Support\BuildingLayout;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /** The one real property (see BuildingLayout). */
    public function run(): void
    {
        Property::create([
            'name' => BuildingLayout::PROPERTY_NAME,
            'address' => BuildingLayout::ADDRESS,
        ]);
    }
}
