<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Services\RoomGenerationService;
use App\Support\BuildingLayout;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /** 14 rooms in the one building: 101–107 and 201–207. */
    public function run(): void
    {
        (new RoomGenerationService)->generate(
            Property::orderBy('id')->firstOrFail(),
            BuildingLayout::roomCount(),
            BuildingLayout::MONTHLY_RATE,
            roomsPerFloor: BuildingLayout::ROOMS_PER_FLOOR,
        );
    }
}
