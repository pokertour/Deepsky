<?php

namespace App\Data\Planning;

final readonly class PlannerData
{
    /** @param list<PlanSlot> $slots */
    public function __construct(
        public TonightData $tonight,
        public array $slots,
    ) {}

    public function usefulImagingMinutes(): int
    {
        return array_sum(array_map(fn (PlanSlot $slot): int => $slot->durationMinutes(), $this->slots));
    }
}
