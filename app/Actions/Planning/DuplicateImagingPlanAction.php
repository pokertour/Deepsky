<?php

namespace App\Actions\Planning;

use App\Enums\ImagingPlanStatus;
use App\Models\ImagingPlan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DuplicateImagingPlanAction
{
    public function execute(ImagingPlan $plan, CarbonImmutable $date, ?string $notes = null): ImagingPlan
    {
        return DB::transaction(function () use ($plan, $date, $notes): ImagingPlan {
            $plan->loadMissing('targets');
            $dayOffset = CarbonImmutable::parse($plan->date->format('Y-m-d'))
                ->diffInDays(CarbonImmutable::parse($date->format('Y-m-d')), false);

            $copy = ImagingPlan::create([
                'date' => $date->format('Y-m-d'),
                'observing_location_id' => $plan->observing_location_id,
                'equipment_setup_id' => $plan->equipment_setup_id,
                'status' => ImagingPlanStatus::Planned,
                'notes' => $notes ?? $plan->notes,
            ]);

            foreach ($plan->targets as $target) {
                $copy->targets()->create([
                    'celestial_object_id' => $target->celestial_object_id,
                    'planned_start_at' => $target->planned_start_at->addDays($dayOffset),
                    'planned_end_at' => $target->planned_end_at->addDays($dayOffset),
                    'position' => $target->position,
                    'score_snapshot' => $target->score_snapshot,
                    'metadata' => $target->metadata,
                ]);
            }

            return $copy->load(['targets.celestialObject', 'observingLocation', 'equipmentSetup']);
        });
    }
}
