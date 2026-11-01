<?php

namespace Database\Factories;

use App\Models\ObservingLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ObservingLocation> */
class ObservingLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'region' => fake()->state(),
            'latitude' => fake()->randomFloat(4, 42.5, 51),
            'longitude' => fake()->randomFloat(4, -4.5, 7.5),
            'altitude' => fake()->numberBetween(0, 2500),
            'timezone' => 'Europe/Paris',
            'is_default' => false,
            'bortle_class' => null,
            'horizon_mask' => null,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
