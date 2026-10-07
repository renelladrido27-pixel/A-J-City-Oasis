<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Property>
 */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'A&J CITY OASIS - '.fake()->unique()->word(),
            'address' => 'Koronadal City, South Cotabato',
        ];
    }
}
