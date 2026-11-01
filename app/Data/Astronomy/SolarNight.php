<?php

namespace App\Data\Astronomy;

use Carbon\CarbonImmutable;

final readonly class SolarNight
{
    public function __construct(
        public ?CarbonImmutable $sunset,
        public ?CarbonImmutable $sunrise,
        public ?CarbonImmutable $civilTwilightEnd,
        public ?CarbonImmutable $civilTwilightStart,
        public ?CarbonImmutable $nauticalTwilightEnd,
        public ?CarbonImmutable $nauticalTwilightStart,
        public ?CarbonImmutable $astronomicalNightStart,
        public ?CarbonImmutable $astronomicalNightEnd,
    ) {}

    public function hasAstronomicalNight(): bool
    {
        return $this->astronomicalNightStart !== null && $this->astronomicalNightEnd !== null;
    }

    /**
     * Fenêtre d'acquisition : la nuit astronomique, ou à défaut la nuit nautique
     * (Soleil sous -12°), seule nuit disponible autour du solstice au nord de 48,6°.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}|null
     */
    public function observingWindow(): ?array
    {
        if ($this->hasAstronomicalNight()) {
            return [$this->astronomicalNightStart, $this->astronomicalNightEnd];
        }

        if ($this->nauticalTwilightEnd !== null && $this->nauticalTwilightStart !== null) {
            return [$this->nauticalTwilightEnd, $this->nauticalTwilightStart];
        }

        return null;
    }

    public function usesNauticalFallback(): bool
    {
        return ! $this->hasAstronomicalNight() && $this->observingWindow() !== null;
    }
}
