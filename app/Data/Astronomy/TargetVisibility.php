<?php

namespace App\Data\Astronomy;

use Carbon\CarbonImmutable;

final readonly class TargetVisibility
{
    /**
     * @param  list<VisibilityPoint>  $timeline
     * @param  array<int, int>  $minutesAboveAltitude
     */
    public function __construct(
        public array $timeline,
        public ?CarbonImmutable $rise,
        public ?CarbonImmutable $set,
        public ?CarbonImmutable $transit,
        public ?float $maxAltitudeDeg,
        public array $minutesAboveAltitude,
    ) {}
}
