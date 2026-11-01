<?php

namespace App\Actions\Planning;

use App\Data\Planning\PlannerData;
use App\Enums\ImagingPlanStatus;
use App\Models\ImagingPlan;
use Illuminate\Support\Facades\DB;

class SaveImagingPlanAction
{
    public function execute(PlannerData $planner, ?string $notes): ImagingPlan
    {
        return DB::transaction(function () use ($planner, $notes): ImagingPlan {
            $plan = ImagingPlan::create([
                'date' => $planner->tonight->date,
                'observing_location_id' => $planner->tonight->location?->id,
                'equipment_setup_id' => $planner->tonight->equipmentSetup?->id,
                'status' => ImagingPlanStatus::Planned,
                'notes' => $notes,
            ]);

            foreach ($planner->slots as $position => $slot) {
                $plan->targets()->create([
                    'celestial_object_id' => $slot->target->id,
                    'planned_start_at' => $slot->startsAt,
                    'planned_end_at' => $slot->endsAt,
                    'position' => $position + 1,
                    'score_snapshot' => $slot->score,
                ]);
            }

            return $plan->load(['targets.celestialObject', 'observingLocation', 'equipmentSetup']);
        });
    }
}
