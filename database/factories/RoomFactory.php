<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'property_id' => Property::factory(),
            'room_number' => (string) fake()->unique()->numberBetween(100, 999),
            'floor' => 1,
            'monthly_rate' => 3500,
            'status' => 'vacant',
        ];
    }
}
