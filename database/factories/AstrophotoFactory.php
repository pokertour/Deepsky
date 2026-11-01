<?php

namespace Database\Factories;

use App\Models\Astrophoto;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Models\SetupItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Astrophoto>
 */
class AstrophotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'target' => fake()->bothify('M ##'),
            'captured_at' => fake()->dateTimeBetween('-2 years'),
            'exposure' => (string) fake()->numberBetween(1800, 50000),
            'gain' => fake()->numberBetween(0, 300),
            'offset' => fake()->numberBetween(0, 100),
            'sensor_temp' => (string) fake()->numberBetween(-20, 0),
            'focal_length' => fake()->numberBetween(135, 2000),
            'filters' => fake()->randomElement(['L-RGB', 'SHO', 'HOO']),
            'description' => fake()->sentence(),
            'location_tag' => fake()->city(),
            'tags' => fake()->randomElements(['galaxy', 'nebula', 'cluster'], 2),
            'camera_id' => SetupItem::factory()->camera(),
            'mount_id' => SetupItem::factory()->mount(),
            'optical_tube_id' => SetupItem::factory()->opticalTube(),
        ];
    }

    public function withEquipmentSetup(?EquipmentSetup $setup = null): static
    {
        return $this->state(fn (): array => [
            'equipment_setup_id' => $setup?->id ?? EquipmentSetup::factory(),
            'camera_id' => null,
            'mount_id' => null,
            'optical_tube_id' => null,
        ]);
    }

    public function atObservingLocation(?ObservingLocation $location = null): static
    {
        return $this->state(fn (): array => [
            'observing_location_id' => $location?->id ?? ObservingLocation::factory(),
            'location_tag' => null,
        ]);
    }
}
