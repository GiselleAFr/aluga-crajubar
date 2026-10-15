<?php

namespace Database\Factories;

use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Property> */
class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => 'Imóvel '.fake()->streetName(),
            'address' => fake()->address(),
            'place_id' => 'ChIJ'.fake()->bothify('????????????'),
            'latitude' => fake()->latitude(-8.5, -2.5),
            'longitude' => fake()->longitude(-41, -37),
        ];
    }
}
