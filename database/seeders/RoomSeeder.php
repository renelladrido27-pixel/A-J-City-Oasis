<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Services\RoomGenerationService;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::orderBy('id')->get();

        // 53 rooms split across the two properties: 27 in Main, 26 in Annex.
        $counts = [27, 26];

        $generator = new RoomGenerationService;

        foreach ($properties as $index => $property) {
            $generator->generate($property, $counts[$index] ?? 0, 3500);
        }
    }
}
