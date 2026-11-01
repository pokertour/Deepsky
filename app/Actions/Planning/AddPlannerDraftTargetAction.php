<?php

namespace App\Actions\Planning;

use App\Data\Planning\PlanSlot;
use App\Models\CelestialObject;
use App\Services\Planning\NightPlanner;
use App\Services\Planning\PlannerDraft;
use Carbon\CarbonImmutable;
use DomainException;

class AddPlannerDraftTargetAction
{
    public function __construct(
        private NightPlanner $planner,
        private PlannerDraft $draft,
    ) {}

    /**
     * Ajoute une cible à la proposition non sauvegardée de la nuit, à la suite
     * des créneaux existants ou sur les horaires imposés.
     */
    public function execute(
        CarbonImmutable $date,
        CelestialObject $target,
        ?string $plannedStart = null,
        ?string $plannedEnd = null,
    ): PlanSlot {
        $generated = $this->draft->apply($this->planner->generate($date));
        $location = $generated->tonight->location;

        if ($location === null) {
            throw new DomainException(__('Configure un lieu d’observation avant d’ajouter une cible.'));
        }

        $setup = $generated->tonight->equipmentSetup;
        $localDate = $date->setTimezone($location->timezone);
        $transition = (int) config('nightscope.planner.transition_minutes', 10);
        $notBefore = $generated->slots !== []
            ? $generated->slots[array_key_last($generated->slots)]->endsAt->addMinutes($transition)
            : $localDate->startOfDay();

        if (filled($plannedStart) && filled($plannedEnd)) {
            $startsAt = $localDate->setTimeFromTimeString($plannedStart);
            $endsAt = $localDate->setTimeFromTimeString($plannedEnd);

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                $endsAt = $endsAt->addDay();
            }

            $slot = new PlanSlot(
                target: $target,
                startsAt: $startsAt,
                endsAt: $endsAt,
                score: $this->planner->suggestSlot($target, $location, $setup, $localDate, $notBefore, strict: false)?->score ?? 0,
            );
        } else {
            $slot = $this->planner->suggestSlot($target, $location, $setup, $localDate, $notBefore)
                ?? $this->planner->suggestSlot($target, $location, $setup, $localDate, $localDate->startOfDay(), strict: false);

            if ($slot === null) {
                throw new DomainException(__('Aucun créneau exploitable n’a été trouvé pour cette cible.'));
            }
        }

        $this->draft->add($date, $slot);

        return $slot;
    }
}
