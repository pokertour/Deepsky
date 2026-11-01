<?php

namespace App\Services\Planning;

use App\Data\Planning\PlannerData;
use App\Data\Planning\PlanSlot;
use App\Models\CelestialObject;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Session;

class PlannerDraft
{
    public function sessionKey(CarbonImmutable $date): string
    {
        return 'nightscope.planner.draft.'.$date->format('Y-m-d');
    }

    /** @return array{excluded: list<int>, added: list<array{celestial_object_id: int, starts_at: string, ends_at: string, score: int}>} */
    public function get(CarbonImmutable $date): array
    {
        $draft = Session::get($this->sessionKey($date), [
            'excluded' => [],
            'added' => [],
        ]);

        return [
            'excluded' => array_values(array_map('intval', $draft['excluded'] ?? [])),
            'added' => array_values($draft['added'] ?? []),
        ];
    }

    public function exclude(CarbonImmutable $date, int $celestialObjectId): void
    {
        $draft = $this->get($date);
        $draft['excluded'] = array_values(array_unique([...$draft['excluded'], $celestialObjectId]));
        $draft['added'] = array_values(array_filter(
            $draft['added'],
            fn (array $slot): bool => (int) $slot['celestial_object_id'] !== $celestialObjectId,
        ));
        Session::put($this->sessionKey($date), $draft);
    }

    public function add(CarbonImmutable $date, PlanSlot $slot): void
    {
        $draft = $this->get($date);
        $draft['excluded'] = array_values(array_filter(
            $draft['excluded'],
            fn (int $id): bool => $id !== $slot->target->id,
        ));
        $draft['added'] = array_values(array_filter(
            $draft['added'],
            fn (array $existing): bool => (int) $existing['celestial_object_id'] !== $slot->target->id,
        ));
        $draft['added'][] = [
            'celestial_object_id' => $slot->target->id,
            'starts_at' => $slot->startsAt->toIso8601String(),
            'ends_at' => $slot->endsAt->toIso8601String(),
            'score' => $slot->score,
        ];
        Session::put($this->sessionKey($date), $draft);
    }

    public function clear(CarbonImmutable $date): void
    {
        Session::forget($this->sessionKey($date));
    }

    public function apply(PlannerData $planner): PlannerData
    {
        $date = $planner->tonight->date;
        $draft = $this->get($date);
        $excluded = $draft['excluded'];

        $slots = array_values(array_filter(
            $planner->slots,
            fn (PlanSlot $slot): bool => ! in_array($slot->target->id, $excluded, true),
        ));

        $existingIds = array_map(fn (PlanSlot $slot): int => $slot->target->id, $slots);

        foreach ($draft['added'] as $added) {
            $targetId = (int) $added['celestial_object_id'];

            if (in_array($targetId, $existingIds, true) || in_array($targetId, $excluded, true)) {
                continue;
            }

            $target = CelestialObject::query()->find($targetId);

            if (! $target) {
                continue;
            }

            $slots[] = new PlanSlot(
                target: $target,
                startsAt: CarbonImmutable::parse($added['starts_at']),
                endsAt: CarbonImmutable::parse($added['ends_at']),
                score: (int) $added['score'],
                acquisition: null,
            );
            $existingIds[] = $targetId;
        }

        usort(
            $slots,
            fn (PlanSlot $left, PlanSlot $right): int => $left->startsAt <=> $right->startsAt,
        );

        return new PlannerData($planner->tonight, $slots);
    }
}
