<?php

namespace App\Data\Planning;

use App\Models\CelestialObject;
use Carbon\CarbonImmutable;

final readonly class PlanSlot
{
    public function __construct(
        public CelestialObject $target,
        public CarbonImmutable $startsAt,
        public CarbonImmutable $endsAt,
        public int $score,
        public ?AcquisitionSuggestion $acquisition = null,
    ) {}

    public function durationMinutes(): int
    {
        return (int) $this->startsAt->diffInMinutes($this->endsAt);
    }
}
