<?php

namespace App\Actions\Planning;

use App\Enums\ImagingPlanStatus;
use App\Models\CelestialObject;
use App\Models\ImagingPlan;
use App\Models\ImagingPlanTarget;
use App\Services\Planning\NightPlanner;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class AddImagingPlanTargetAction
{
    public function __construct(private NightPlanner $planner) {}

    public function execute(
        ImagingPlan $plan,
        CelestialObject $target,
        ?CarbonImmutable $startsAt = null,
        ?CarbonImmutable $endsAt = null,
    ): ImagingPlanTarget {
        if ($plan->status === ImagingPlanStatus::Active) {
            throw new DomainException(__('Impossible d’ajouter une cible à un plan en session active.'));
        }

        $plan->loadMissing(['targets', 'observingLocation', 'equipmentSetup']);

        if ($plan->observingLocation === null) {
            throw new DomainException(__('Ce plan n’a pas de lieu d’observation.'));
        }

        if ($plan->targets->contains(fn (ImagingPlanTarget $existing): bool => $existing->celestial_object_id === $target->id)) {
            throw new DomainException(__('Cette cible est déjà présente dans le plan.'));
        }

        $date = CarbonImmutable::parse($plan->date->format('Y-m-d'), $plan->observingLocation->timezone);
        $transition = (int) config('nightscope.planner.transition_minutes', 10);
        $lastEnd = $plan->targets->last()?->planned_end_at;
        $notBefore = $lastEnd
            ? CarbonImmutable::parse($lastEnd)->addMinutes($transition)
            : $date->startOfDay();

        if ($startsAt !== null && $endsAt !== null) {
            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw new DomainException(__('L’heure de fin doit être après l’heure de début.'));
            }

            $slotStart = $startsAt;
            $slotEnd = $endsAt;
            $score = $this->planner->suggestSlot(
                $target,
                $plan->observingLocation,
                $plan->equipmentSetup,
                $date,
                $notBefore,
            )?->score ?? 0;
        } else {
            $slot = $this->planner->suggestSlot(
                $target,
                $plan->observingLocation,
                $plan->equipmentSetup,
                $date,
                $notBefore,
            );

            if ($slot === null) {
                $slot = $this->planner->suggestSlot(
                    $target,
                    $plan->observingLocation,
                    $plan->equipmentSetup,
                    $date,
                    $date->startOfDay(),
                    strict: false,
                );
            }

            if ($slot === null) {
                throw new DomainException(__('Aucun créneau exploitable n’a été trouvé pour cette cible.'));
            }

            $slotStart = $slot->startsAt;
            $slotEnd = $slot->endsAt;
            $score = $slot->score;
        }

        return DB::transaction(function () use ($plan, $target, $slotStart, $slotEnd, $score): ImagingPlanTarget {
            $position = ((int) $plan->targets()->max('position')) + 1;

            return $plan->targets()->create([
                'celestial_object_id' => $target->id,
                'planned_start_at' => $slotStart,
                'planned_end_at' => $slotEnd,
                'position' => $position,
                'score_snapshot' => $score,
                'metadata' => ['source' => 'manual'],
            ]);
        });
    }
}
