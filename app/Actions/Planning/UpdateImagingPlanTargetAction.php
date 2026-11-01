<?php

namespace App\Actions\Planning;

use App\Enums\ImagingPlanStatus;
use App\Models\ImagingPlan;
use App\Models\ImagingPlanTarget;
use Carbon\CarbonImmutable;
use DomainException;

class UpdateImagingPlanTargetAction
{
    public function execute(
        ImagingPlan $plan,
        ImagingPlanTarget $target,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
    ): ImagingPlanTarget {
        if ($plan->status === ImagingPlanStatus::Active) {
            throw new DomainException(__('Impossible de modifier les horaires pendant une session active.'));
        }

        if ($target->imaging_plan_id !== $plan->id) {
            throw new DomainException(__('Cette cible n’appartient pas à ce plan.'));
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new DomainException(__('L’heure de fin doit être après l’heure de début.'));
        }

        $target->update([
            'planned_start_at' => $startsAt,
            'planned_end_at' => $endsAt,
        ]);

        return $target->fresh() ?? $target;
    }
}
