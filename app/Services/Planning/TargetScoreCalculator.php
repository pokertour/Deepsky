<?php

namespace App\Services\Planning;

use App\Data\Astronomy\MoonData;
use App\Data\Astronomy\TargetVisibility;
use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\FramingResult;
use App\Data\Planning\TargetHistory;
use App\Data\Planning\TargetScore;
use App\Data\Weather\WeatherScore;
use App\Enums\FramingRating;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Services\Astronomy\CelestialPreview;
use App\Services\Astronomy\CoordinateConverter;
use App\Services\Astronomy\PointingDirection;
use App\Services\Equipment\FramingCalculator;
use Carbon\CarbonImmutable;

class TargetScoreCalculator
{
    public function __construct(
        private CoordinateConverter $coordinates,
        private PointingDirection $pointingDirection,
        private CelestialPreview $preview,
        private FramingCalculator $framing,
        private EquipmentScoreCalculator $equipmentScore,
        private HistoricalScoreCalculator $historicalScore,
        private AcquisitionSuggestionCalculator $acquisition,
        private InterestScoreCalculator $interest,
    ) {}

    /**
     * @param  list<string>  $filterTypes
     * @param  array<string, int>  $photosByType
     */
    public function calculate(
        CelestialObject $target,
        TargetVisibility $visibility,
        MoonData $moon,
        ?EquipmentOptics $optics,
        ?WeatherScore $weather = null,
        ?TargetHistory $history = null,
        array $filterTypes = [],
        array $photosByType = [],
        int $totalPhotos = 0,
        ?CarbonImmutable $referenceDate = null,
        ?EquipmentSetup $setup = null,
        ?int $bortleClass = null,
    ): TargetScore {
        $visibilityScore = min(100, ($visibility->minutesAboveAltitude[30] ?? 0) / 360 * 100);
        // Au-delà d'environ 60° le gain (extinction, turbulence) devient négligeable : le score sature.
        $fullScoreAltitude = max(1.0, (float) config('nightscope.scores.altitude_full_score_deg', 60));
        $altitudeScore = min(100, max(0, $visibility->maxAltitudeDeg ?? 0) / $fullScoreAltitude * 100);
        $moonSeparation = $this->coordinates->angularSeparationDeg(
            $target->ra_deg,
            $target->dec_deg,
            $moon->rightAscensionDeg,
            $moon->declinationDeg,
        );
        $moonPenalty = $moon->illuminationPercent / 100 * (1 - $moonSeparation / 180);
        $moonScore = max(0, 100 * (1 - $moonPenalty));
        $framing = $this->framing($target, $optics);
        $framingScore = match ($framing?->rating) {
            FramingRating::Excellent => 100,
            FramingRating::Good => 80,
            FramingRating::Small => 40,
            FramingRating::TooLarge => 20,
            FramingRating::Mosaic => 10,
            null => 50,
        };
        $history ??= new TargetHistory(
            photoCount: (int) ($target->astrophotos_count ?? 0),
            sessionCount: (int) ($target->astrophotos_count ?? 0) > 0 ? 1 : 0,
            totalIntegrationSeconds: 0,
            lastCapturedAt: null,
        );
        $interestScore = $this->interest->calculate($target);
        $components = [
            'interest' => $interestScore,
            'visibility' => $visibilityScore,
            'altitude' => $altitudeScore,
            'moon' => $moonScore,
            'framing' => $framingScore,
            'equipment' => $this->equipmentScore->calculate($target, $optics, $filterTypes),
            'history' => $this->historicalScore->calculate(
                $history,
                $target->type,
                $photosByType,
                $totalPhotos,
                $referenceDate,
            ),
        ];

        if ($weather) {
            $components['weather'] = $weather->score;
        }

        $weights = (array) config('nightscope.scores.target');
        $totalWeight = array_sum(array_map(fn (string $key): float => (float) ($weights[$key] ?? 0), array_keys($components)));
        $weightedScore = $totalWeight > 0
            ? array_sum(array_map(fn (float|int $value, string $key): float => $value * (float) ($weights[$key] ?? 0), $components, array_keys($components))) / $totalWeight
            : 0;
        $neverPhotographed = $history->photoCount === 0;
        $transitPoint = collect($visibility->timeline)->first(
            fn ($point): bool => $visibility->transit?->equalTo($point->at) === true,
        );
        $pointingAzimuth = $transitPoint?->azimuthDeg;

        return new TargetScore(
            target: $target,
            score: min(100, (int) round($weightedScore + ($neverPhotographed ? (float) ($weights['never_photographed_bonus'] ?? 0) * $interestScore / 100 : 0))),
            moonSeparationDeg: $moonSeparation,
            pointingAzimuthDeg: $pointingAzimuth,
            pointingDirection: $pointingAzimuth === null ? null : $this->pointingDirection->fromAzimuth($pointingAzimuth),
            previewImageUrl: $this->preview->url($target),
            optics: $optics,
            visibility: $visibility,
            framing: $framing,
            neverPhotographed: $neverPhotographed,
            acquisition: $this->acquisition->suggest(
                $target,
                $setup,
                $optics,
                $filterTypes,
                $moon->illuminationPercent,
                $moonSeparation,
                $bortleClass,
            ),
        );
    }

    private function framing(CelestialObject $target, ?EquipmentOptics $optics): ?FramingResult
    {
        if (! $optics || ! $target->angular_width_arcmin || ! $target->angular_height_arcmin) {
            return null;
        }

        return $this->framing->calculate(
            $target->angular_width_arcmin,
            $target->angular_height_arcmin,
            $optics,
        );
    }
}
