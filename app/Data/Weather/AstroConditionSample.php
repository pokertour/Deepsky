<?php

namespace App\Data\Weather;

use Carbon\CarbonImmutable;

final readonly class AstroConditionSample
{
    public function __construct(
        public CarbonImmutable $at,
        public ?float $seeingIndex,
        public ?float $transparencyIndex,
    ) {}
}
