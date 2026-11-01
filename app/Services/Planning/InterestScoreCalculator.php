<?php

namespace App\Services\Planning;

use App\Models\CelestialObject;

/**
 * Intérêt photographique d'un objet (0-100), indépendant de la nuit : notoriété
 * (Messier, Caldwell, nom d'usage, catalogue de nébuleuses), éclat et taille.
 * Sans cet axe, un petit amas haut toute la nuit vaudrait autant que M31.
 */
class InterestScoreCalculator
{
    public function calculate(CelestialObject $target): float
    {
        $weights = (array) config('nightscope.scores.interest');
        $components = [
            'notoriety' => $this->notoriety($target),
            'size' => $this->size($target),
        ];

        // Beaucoup de grandes nébuleuses n'ont pas de magnitude dans OpenNGC : on ne les pénalise pas.
        if ($target->magnitude !== null) {
            $components['brightness'] = min(100.0, max(0.0, (12 - $target->magnitude) / 8 * 100));
        }

        $totalWeight = array_sum(array_map(fn (string $key): float => (float) ($weights[$key] ?? 0), array_keys($components)));

        return $totalWeight > 0
            ? array_sum(array_map(fn (float $value, string $key): float => $value * (float) ($weights[$key] ?? 0), $components, array_keys($components))) / $totalWeight
            : 0.0;
    }

    private function notoriety(CelestialObject $target): float
    {
        $aliases = collect($target->alternative_names ?? [])->map(fn (mixed $alias): string => (string) $alias);

        return match (true) {
            $target->catalog === 'M' => 100.0,
            $aliases->contains(fn (string $alias): bool => preg_match('/^(C\s*\d+|Caldwell\s+\d+)$/i', $alias) === 1) => 85.0,
            filled($target->name) => 60.0,
            $aliases->contains(fn (string $alias): bool => preg_match('/^(LBN|LDN|Sh2|Barnard|B)\s*-?\d+/i', $alias) === 1) => 45.0,
            default => 0.0,
        };
    }

    /**
     * Échelle logarithmique sur le grand axe : 1′ → 0, 10′ → ~50, 90′ et plus → 100.
     */
    private function size(CelestialObject $target): float
    {
        $largest = max((float) $target->angular_width_arcmin, (float) $target->angular_height_arcmin);

        if ($largest <= 1) {
            return 0.0;
        }

        return min(100.0, log10($largest) / log10(90) * 100);
    }
}
