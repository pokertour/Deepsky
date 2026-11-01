<?php

namespace App\Actions\Planning;

use App\Enums\ImagingPlanStatus;
use App\Models\ImagingPlan;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class UpdateImagingPlanAction
{
    /**
     * @param  array{date?: string, observing_location_id?: int, equipment_setup_id?: int|null, notes?: string|null}  $data
     */
    public function execute(ImagingPlan $plan, array $data): ImagingPlan
    {
        if ($plan->status === ImagingPlanStatus::Active) {
            throw new DomainException(__('Impossible de modifier un plan en session active.'));
        }

        return DB::transaction(function () use ($plan, $data): ImagingPlan {
            $plan->loadMissing('targets');
            $originalDate = CarbonImmutable::parse($plan->date->format('Y-m-d'));
            $nextDate = isset($data['date'])
                ? CarbonImmutable::parse($data['date'])
                : $originalDate;
            $dayOffset = $originalDate->diffInDays($nextDate, false);

            $plan->fill([
                'date' => $nextDate->format('Y-m-d'),
                'observing_location_id' => $data['observing_location_id'] ?? $plan->observing_location_id,
                'equipment_setup_id' => array_key_exists('equipment_setup_id', $data)
                    ? $data['equipment_setup_id']
                    : $plan->equipment_setup_id,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $plan->notes,
            ]);
            $plan->save();

            if ($dayOffset !== 0) {
                foreach ($plan->targets as $target) {
                    $target->update([
                        'planned_start_at' => $target->planned_start_at->addDays($dayOffset),
                        'planned_end_at' => $target->planned_end_at->addDays($dayOffset),
                    ]);
                }
            }

            return $plan->fresh(['targets.celestialObject', 'observingLocation', 'equipmentSetup']) ?? $plan;
        });
    }
}
