<?php

namespace App\Services\Planning;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Planning\AcquisitionSuggestion;
use App\Data\Planning\FilterRecommendation;
use App\Enums\AcquisitionSuggestionSource;
use App\Enums\FilterType;
use App\Enums\SetupItemType;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use Illuminate\Support\Collection;

class AcquisitionSuggestionCalculator
{
    public function __construct(
        private FilterRecommendationCalculator $filterRecommendations,
    ) {}

    /**
     * @param  list<string>  $filterTypes
     */
    public function suggest(
        CelestialObject $target,
        ?EquipmentSetup $setup,
        ?EquipmentOptics $optics,
        array $filterTypes = [],
        float $moonIlluminationPercent = 0.0,
        float $moonSeparationDeg = 180.0,
        ?int $bortleClass = null,
    ): ?AcquisitionSuggestion {
        if (! $setup && ! $optics) {
            return null;
        }

        $items = $this->enabledItems($setup);
        $camera = $items->first(fn (SetupItem $item): bool => $item->type === SetupItemType::Camera);
        $filters = $this->filterRecommendations->recommend(
            $target,
            $setup,
            $moonIlluminationPercent,
            $moonSeparationDeg,
            $bortleClass,
        );
        [$gain, $gainSource] = $this->resolveGain($setup, $target, $camera);
        [$exposure, $exposureSource] = $this->resolveExposure(
            $target,
            $optics,
            $filterTypes,
            $camera,
            $items,
            $filters,
        );

        if ($gain === null && $exposure === null && $filters === []) {
            return null;
        }

        return new AcquisitionSuggestion(
            gain: $gain,
            subExposureSeconds: $exposure,
            gainSource: $gainSource,
            exposureSource: $exposureSource,
            summary: $this->summary($gain, $exposure, $gainSource, $exposureSource, $filters),
            filters: $filters,
        );
    }

    /**
     * @return array{0: ?int, 1: AcquisitionSuggestionSource}
     */
    private function resolveGain(?EquipmentSetup $setup, CelestialObject $target, ?SetupItem $camera): array
    {
        $preferred = $camera?->specs->preferredGain();

        if ($preferred !== null) {
            return [$preferred, AcquisitionSuggestionSource::Preferred];
        }

        $historical = $this->historicalGain($setup, $target);

        if ($historical !== null) {
            return [$historical, AcquisitionSuggestionSource::Historical];
        }

        $cooled = $camera?->specs->cooled();
        $default = $cooled === false
            ? (int) config('nightscope.acquisition.default_uncooled_gain', 0)
            : (int) config('nightscope.acquisition.default_cooled_gain', 100);

        return [$default, AcquisitionSuggestionSource::Heuristic];
    }

    /**
     * @param  list<string>  $filterTypes
     * @param  Collection<int, SetupItem>  $items
     * @param  list<FilterRecommendation>  $filters
     * @return array{0: ?int, 1: AcquisitionSuggestionSource}
     */
    private function resolveExposure(
        CelestialObject $target,
        ?EquipmentOptics $optics,
        array $filterTypes,
        ?SetupItem $camera,
        Collection $items,
        array $filters,
    ): array {
        $preferred = $camera?->specs->typicalSubExposureSeconds();

        if ($preferred !== null && $preferred > 0) {
            return [$preferred, AcquisitionSuggestionSource::Preferred];
        }

        if (! $optics) {
            return [null, AcquisitionSuggestionSource::Heuristic];
        }

        $primary = $filters[0] ?? null;
        $typesForExposure = $primary
            ? [$primary->filterType->value]
            : $filterTypes;
        $bandwidth = $primary?->bandwidthNm ?? $this->narrowestBandwidthNm($items);

        return [
            $this->heuristicExposureSeconds(
                $target,
                $optics->focalRatio,
                $typesForExposure,
                $bandwidth,
            ),
            AcquisitionSuggestionSource::Heuristic,
        ];
    }

    /**
     * @param  list<string>  $filterTypes
     */
    private function heuristicExposureSeconds(
        CelestialObject $target,
        float $focalRatio,
        array $filterTypes,
        ?float $bandwidthNm,
    ): int {
        $hasNarrowband = $this->hasAny($filterTypes, [FilterType::Narrowband->value]);
        $hasDualBand = $this->hasAny($filterTypes, [FilterType::DualBand->value]);

        $base = match (true) {
            $hasNarrowband => (float) config('nightscope.acquisition.base_narrowband_seconds', 300),
            $hasDualBand => (float) config('nightscope.acquisition.base_dual_band_seconds', 180),
            default => (float) config('nightscope.acquisition.base_broadband_seconds', 120),
        };

        if ($bandwidthNm !== null && $bandwidthNm > 0 && $bandwidthNm <= 7.0) {
            $base *= 1.25;
        }

        $scaled = $base * (($focalRatio / 5.0) ** 2);
        $magnitude = $target->magnitude;

        if ($magnitude !== null && $magnitude < 6.0) {
            $scaled *= 0.7;
        } elseif ($magnitude !== null && $magnitude > 10.0) {
            $scaled *= 1.2;
        }

        $min = (int) config('nightscope.acquisition.minimum_sub_exposure_seconds', 30);
        $max = (int) config('nightscope.acquisition.maximum_sub_exposure_seconds', 600);

        return $this->snapExposure((int) round(max($min, min($max, $scaled))));
    }

    private function historicalGain(?EquipmentSetup $setup, CelestialObject $target): ?int
    {
        if (! $setup) {
            return null;
        }

        $forTarget = Astrophoto::query()
            ->where('equipment_setup_id', $setup->id)
            ->where('celestial_object_id', $target->id)
            ->whereNotNull('gain')
            ->orderByDesc('captured_at')
            ->limit(20)
            ->pluck('gain');

        if ($forTarget->isNotEmpty()) {
            return (int) round((float) $forTarget->median());
        }

        $forSetup = Astrophoto::query()
            ->where('equipment_setup_id', $setup->id)
            ->whereNotNull('gain')
            ->orderByDesc('captured_at')
            ->limit(50)
            ->pluck('gain');

        return $forSetup->isEmpty() ? null : (int) round((float) $forSetup->median());
    }

    /** @return Collection<int, SetupItem> */
    private function enabledItems(?EquipmentSetup $setup): Collection
    {
        if (! $setup) {
            return collect();
        }

        return $setup->relationLoaded('enabledSetupItems')
            ? $setup->enabledSetupItems
            : $setup->setupItems()->wherePivot('enabled', true)->get();
    }

    /** @param  Collection<int, SetupItem>  $items */
    private function narrowestBandwidthNm(Collection $items): ?float
    {
        $bandwidths = $items
            ->filter(fn (SetupItem $item): bool => $item->type === SetupItemType::Filter)
            ->map(fn (SetupItem $item): ?float => $item->specs->bandwidthNm())
            ->filter()
            ->values();

        return $bandwidths->isEmpty() ? null : (float) $bandwidths->min();
    }

    /** @param  list<string>  $filterTypes @param  list<string>  $needles */
    private function hasAny(array $filterTypes, array $needles): bool
    {
        return array_intersect($filterTypes, $needles) !== [];
    }

    private function snapExposure(int $seconds): int
    {
        $steps = [30, 45, 60, 90, 120, 150, 180, 240, 300, 360, 480, 600];
        $closest = $steps[0];

        foreach ($steps as $step) {
            if (abs($step - $seconds) < abs($closest - $seconds)) {
                $closest = $step;
            }
        }

        return $closest;
    }

    /**
     * @param  list<FilterRecommendation>  $filters
     */
    private function summary(
        ?int $gain,
        ?int $exposure,
        AcquisitionSuggestionSource $gainSource,
        AcquisitionSuggestionSource $exposureSource,
        array $filters,
    ): string {
        $parts = [];
        $primary = $filters[0] ?? null;

        if ($primary) {
            $parts[] = 'filtre '.$primary->displayName();
        }

        if ($gain !== null) {
            $parts[] = 'gain '.$gain.' ('.$gainSource->label().')';
        }

        if ($exposure !== null) {
            $parts[] = 'pose '.$exposure.' s ('.$exposureSource->label().')';
        }

        return implode(' · ', $parts);
    }
}
