<?php

namespace App\Services\Planning;

use App\Data\Planning\TargetHistory;
use Carbon\CarbonImmutable;

class HistoricalScoreCalculator
{
    /**
     * @param  array<string, int>  $photosByType
     */
    public function calculate(
        TargetHistory $history,
        ?string $targetType = null,
        array $photosByType = [],
        int $totalPhotos = 0,
        ?CarbonImmutable $referenceDate = null,
    ): float {
        if ($history->photoCount === 0) {
            return 100.0;
        }

        $weights = $this->weights();
        $referenceDate ??= CarbonImmutable::now();
        $daysSince = $history->lastCapturedAt
            ? max(0, $history->lastCapturedAt->diffInDays($referenceDate))
            : (int) ($weights['fresh_after_days'] ?? 180);
        $freshAfterDays = max(1, (int) ($weights['fresh_after_days'] ?? 180));
        $recencyScore = min(100.0, $daysSince / $freshAfterDays * 100);

        $hours = $history->totalIntegrationSeconds / 3600;
        $softCapHours = max(1.0, (float) ($weights['integration_soft_cap_hours'] ?? 10));
        $integrationScore = max(20.0, 100.0 - ($hours / $softCapHours * 80.0));

        $type = strtolower((string) $targetType);
        $typeCount = $photosByType[$type] ?? 0;
        $typeShare = $totalPhotos > 0 ? $typeCount / $totalPhotos : 0.0;
        $diversityScore = (1 - min(1.0, $typeShare * 3)) * 100;

        $components = [
            'recency' => $recencyScore,
            'integration' => $integrationScore,
            'diversity' => $diversityScore,
        ];
        $componentWeights = [
            'recency' => (float) ($weights['recency'] ?? 0.40),
            'integration' => (float) ($weights['integration'] ?? 0.35),
            'diversity' => (float) ($weights['diversity'] ?? 0.25),
        ];
        $totalWeight = array_sum($componentWeights);

        return $totalWeight > 0
            ? array_sum(array_map(
                fn (float $value, string $key): float => $value * $componentWeights[$key],
                $components,
                array_keys($components),
            )) / $totalWeight
            : 50.0;
    }

    /** @return array{recency: float, integration: float, diversity: float, fresh_after_days: int, integration_soft_cap_hours: float} */
    private function weights(): array
    {
        $defaults = [
            'recency' => 0.40,
            'integration' => 0.35,
            'diversity' => 0.25,
            'fresh_after_days' => 180,
            'integration_soft_cap_hours' => 10.0,
        ];

        if (! function_exists('app') || ! app()->bound('config')) {
            return $defaults;
        }

        return array_replace($defaults, (array) config('nightscope.scores.history', []));
    }
}
