<?php

namespace App\Services\Planning;

use App\Data\Equipment\EquipmentOptics;
use App\Enums\SetupItemType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;

class EquipmentScoreCalculator
{
    /**
     * @param  list<string>  $filterTypes
     */
    public function calculate(
        CelestialObject $target,
        ?EquipmentOptics $optics,
        array $filterTypes = [],
    ): float {
        if (! $optics) {
            return 50.0;
        }

        $sizeArcmin = max(
            (float) ($target->angular_width_arcmin ?? 0),
            (float) ($target->angular_height_arcmin ?? 0),
        );
        $type = strtolower((string) ($target->type ?? ''));
        $weights = $this->weights();

        $components = [
            'focal' => $this->focalScore($type, $sizeArcmin, $optics->effectiveFocalLengthMm),
            'sampling' => $this->samplingScore($type, $optics->pixelScaleArcsec),
            'filter' => $this->filterScore($type, $sizeArcmin, $filterTypes),
            'field' => $this->fieldScore($sizeArcmin, $optics->horizontalFieldOfViewDeg * 60),
        ];

        $totalWeight = array_sum($weights);

        return $totalWeight > 0
            ? array_sum(array_map(
                fn (float $value, string $key): float => $value * ($weights[$key] ?? 0),
                $components,
                array_keys($components),
            )) / $totalWeight
            : 50.0;
    }

    /** @return list<string> */
    public function filterTypesFor(?EquipmentSetup $setup): array
    {
        if (! $setup) {
            return [];
        }

        $items = $setup->relationLoaded('enabledSetupItems')
            ? $setup->enabledSetupItems
            : $setup->setupItems()->wherePivot('enabled', true)->get();

        return $items
            ->filter(fn (SetupItem $item): bool => $item->type === SetupItemType::Filter)
            ->map(fn (SetupItem $item): ?string => $item->specs->filterType())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function focalScore(string $type, float $sizeArcmin, float $focalLengthMm): float
    {
        [$idealMin, $idealMax, $okMin, $okMax] = match (true) {
            $type === 'galaxy' => [700.0, 1600.0, 350.0, 2500.0],
            $type === 'nebula' && $sizeArcmin >= 60.0 => [200.0, 550.0, 100.0, 900.0],
            $type === 'nebula' => [600.0, 1400.0, 300.0, 2000.0],
            $type === 'cluster' => [400.0, 1000.0, 200.0, 1600.0],
            default => [300.0, 1200.0, 150.0, 2000.0],
        };

        return $this->rangeScore($focalLengthMm, $idealMin, $idealMax, $okMin, $okMax);
    }

    private function samplingScore(string $type, float $pixelScaleArcsec): float
    {
        [$idealMin, $idealMax, $okMin, $okMax] = match ($type) {
            'galaxy' => [0.6, 1.5, 0.3, 2.5],
            'nebula' => [1.0, 3.0, 0.5, 4.5],
            'cluster' => [0.8, 2.2, 0.4, 3.5],
            default => [0.7, 2.5, 0.3, 4.0],
        };

        return $this->rangeScore($pixelScaleArcsec, $idealMin, $idealMax, $okMin, $okMax);
    }

    /** @param  list<string>  $filterTypes */
    private function filterScore(string $type, float $sizeArcmin, array $filterTypes): float
    {
        if ($filterTypes === []) {
            return 55.0;
        }

        $hasNarrowband = $this->hasAny($filterTypes, ['narrowband', 'dual_band']);
        $hasBroadband = $this->hasAny($filterTypes, ['broadband', 'luminance', 'color', 'uv_ir_cut']);

        return match (true) {
            $type === 'nebula' && $hasNarrowband => 95.0,
            $type === 'nebula' && $hasBroadband && $sizeArcmin >= 60.0 => 80.0,
            $type === 'nebula' && $hasBroadband => 65.0,
            $type === 'galaxy' && $hasBroadband => 90.0,
            $type === 'galaxy' && $hasNarrowband => 35.0,
            $type === 'cluster' && $hasBroadband => 85.0,
            $type === 'cluster' && $hasNarrowband => 45.0,
            default => 60.0,
        };
    }

    private function fieldScore(float $sizeArcmin, float $horizontalFovArcmin): float
    {
        if ($sizeArcmin <= 0 || $horizontalFovArcmin <= 0) {
            return 55.0;
        }

        $fill = $sizeArcmin / $horizontalFovArcmin;

        return match (true) {
            $fill >= 0.25 && $fill <= 0.85 => 100.0,
            $fill >= 0.15 && $fill < 0.25 => 75.0,
            $fill > 0.85 && $fill <= 1.2 => 55.0,
            $fill > 1.2 && $fill <= 2.0 => 30.0,
            $fill < 0.15 => 40.0,
            default => 20.0,
        };
    }

    private function rangeScore(float $value, float $idealMin, float $idealMax, float $okMin, float $okMax): float
    {
        if ($value >= $idealMin && $value <= $idealMax) {
            return 100.0;
        }

        if ($value < $okMin || $value > $okMax) {
            return 15.0;
        }

        if ($value < $idealMin) {
            $span = max(0.0001, $idealMin - $okMin);

            return 40.0 + 60.0 * (($value - $okMin) / $span);
        }

        $span = max(0.0001, $okMax - $idealMax);

        return 40.0 + 60.0 * (($okMax - $value) / $span);
    }

    /** @param  list<string>  $filterTypes @param  list<string>  $needles */
    private function hasAny(array $filterTypes, array $needles): bool
    {
        return array_intersect($filterTypes, $needles) !== [];
    }

    /** @return array{focal: float, sampling: float, filter: float, field: float} */
    private function weights(): array
    {
        $defaults = [
            'focal' => 0.30,
            'sampling' => 0.25,
            'filter' => 0.25,
            'field' => 0.20,
        ];

        if (! function_exists('app') || ! app()->bound('config')) {
            return $defaults;
        }

        return array_replace($defaults, (array) config('nightscope.scores.equipment', []));
    }
}
