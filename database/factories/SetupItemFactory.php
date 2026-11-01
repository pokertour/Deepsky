<?php

namespace Database\Factories;

use App\Enums\SetupItemType;
use App\Models\SetupItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SetupItem>
 */
class SetupItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'type' => fake()->randomElement(SetupItemType::cases()),
            'brand' => fake()->company(),
        ];
    }

    public function camera(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => SetupItemType::Camera]);
    }

    public function mount(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => SetupItemType::Mount]);
    }

    public function opticalTube(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => SetupItemType::OpticalTube]);
    }
}
