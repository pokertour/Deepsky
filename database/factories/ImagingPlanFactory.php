<?php

namespace Database\Factories;

use App\Enums\ImagingPlanStatus;
use App\Models\EquipmentSetup;
use App\Models\ImagingPlan;
use App\Models\ObservingLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImagingPlan>
 */
class ImagingPlanFactory extends Factory
{
    protected $model = ImagingPlan::class;

    public function definition(): array
    {
        return [
            'date' => fake()->date(),
            'observing_location_id' => ObservingLocation::factory(),
            'equipment_setup_id' => EquipmentSetup::factory(),
            'status' => ImagingPlanStatus::Planned,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
