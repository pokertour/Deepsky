<?php

namespace App\Data\Planning;

final readonly class NightScore
{
    public function __construct(
        public int $score,
        public int $darknessMinutes,
        public float $moonIlluminationPercent,
        public ?int $weatherScore,
    ) {}
}
