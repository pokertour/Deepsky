<?php

namespace App\Rules;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Un fuseau sans rapport avec la longitude décale tous les calculs de nuit
 * (coucher du Soleil sur la mauvaise date, « nuit » de plus de 24 h).
 * Les écarts légitimes (Espagne, Chine de l'Ouest, heure d'été) restent sous 3,5 h.
 */
class TimezoneMatchesLongitude implements DataAwareRule, ValidationRule
{
    private const float MAXIMUM_OFFSET_HOURS = 3.5;

    /** @var array<string, mixed> */
    private array $data = [];

    /** @param  array<string, mixed>  $data */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($this->data['longitude'] ?? null)) {
            return;
        }

        try {
            $zone = new DateTimeZone((string) $value);
        } catch (Throwable) {
            return;
        }

        $solarOffsetHours = (float) $this->data['longitude'] / 15;
        $now = new DateTimeImmutable;
        $zoneOffsetHours = min(
            $zone->getOffset($now->setDate((int) $now->format('Y'), 1, 15)),
            $zone->getOffset($now->setDate((int) $now->format('Y'), 7, 15)),
        ) / 3600;

        if (abs($zoneOffsetHours - $solarOffsetHours) > self::MAXIMUM_OFFSET_HOURS) {
            $fail(sprintf(
                __('Le fuseau %s (UTC%+g) ne correspond pas à la longitude saisie (environ UTC%+d) : vérifie le fuseau ou les coordonnées.'),
                $value,
                $zoneOffsetHours,
                (int) round($solarOffsetHours),
            ));
        }
    }
}
