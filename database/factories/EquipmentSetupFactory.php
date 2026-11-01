<?php

namespace Database\Factories;

use App\Models\EquipmentSetup;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EquipmentSetup> */
class EquipmentSetupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->optional()->sentence(),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
