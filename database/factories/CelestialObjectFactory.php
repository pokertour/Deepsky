<?php

namespace Database\Factories;

use App\Models\CelestialObject;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CelestialObject> */
class CelestialObjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'catalog' => 'NGC',
            'catalog_number' => (string) fake()->unique()->numberBetween(1, 9999),
            'name' => fake()->optional()->words(3, true),
            'alternative_names' => [],
            'type' => fake()->randomElement(['galaxy', 'nebula', 'cluster']),
            'constellation' => fake()->word(),
            'ra_deg' => fake()->randomFloat(7, 0, 359.9999999),
            'dec_deg' => fake()->randomFloat(7, -90, 90),
            'magnitude' => fake()->randomFloat(2, 1, 18),
            'surface_brightness' => null,
            'angular_width_arcmin' => fake()->randomFloat(3, 1, 180),
            'angular_height_arcmin' => fake()->randomFloat(3, 1, 180),
            'distance_ly' => null,
            'metadata' => null,
        ];
    }
}
