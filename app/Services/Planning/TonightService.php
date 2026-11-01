<?php

namespace App\Services\Planning;

use App\Data\Astronomy\MoonData;
use App\Data\Astronomy\SolarNight;
use App\Data\Equipment\EquipmentOptics;
use App\Data\Planning\TargetScore;
use App\Data\Planning\TonightData;
use App\Data\Weather\WeatherScore;
use App\Enums\CelestialObjectType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Services\Astronomy\CoordinateConverter;
use App\Services\Astronomy\LunarCalculator;
use App\Services\Astronomy\SolarCalculator;
use App\Services\Astronomy\VisibilityCalculator;
use App\Services\Equipment\EquipmentCalculator;
use App\Services\Weather\WeatherManager;
use App\Services\Weather\WeatherScoreCalculator;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TonightService
{
    public function __construct(
        private SolarCalculator $solar,
        private LunarCalculator $lunar,
        private VisibilityCalculator $visibility,
        private EquipmentCalculator $equipment,
        private NightScoreCalculator $nightScore,
        private TargetScoreCalculator $targetScore,
        private WeatherManager $weather,
        private WeatherScoreCalculator $weatherScore,
        private HistoricalTargetAnalyzer $history,
        private EquipmentScoreCalculator $equipmentScore,
        private InterestScoreCalculator $interest,
        private CoordinateConverter $coordinates,
    ) {}

    /** @param  list<string>|null  $objectTypes */
    public function build(
        CarbonImmutable $date,
        ?array $objectTypes = null,
        ?int $recommendationLimit = null,
        bool $neverPhotographedOnly = false,
        ?string $search = null,
        ?ObservingLocation $location = null,
        ?EquipmentSetup $setup = null,
        bool $setupSpecified = false,
        ?array $favoriteIds = null,
    ): TonightData {
        $search = trim((string) $search);
        $objectTypes = $this->normalizeObjectTypes($objectTypes);
        $recommendationLimit = $this->normalizeRecommendationLimit($recommendationLimit);
        $location ??= ObservingLocation::query()->where('is_default', true)->first()
            ?? ObservingLocation::query()->oldest('id')->first();
        if (! $setupSpecified) {
            $setup = EquipmentSetup::query()->with('enabledSetupItems')->where('is_default', true)->first()
                ?? EquipmentSetup::query()->with('enabledSetupItems')->oldest('id')->first();
        } elseif ($setup) {
            $setup->loadMissing('enabledSetupItems');
        }

        if (! $location) {
            return new TonightData(
                date: $date,
                location: null,
                equipmentSetup: $setup,
                optics: null,
                night: null,
                moon: null,
                weather: null,
                nightScore: null,
                targets: [],
                objectTypes: $objectTypes,
                recommendationLimit: $recommendationLimit,
                neverPhotographedOnly: $neverPhotographedOnly,
                search: $search,
                favoritesOnly: $favoriteIds !== null,
            );
        }

        $date = $date->setTimezone($location->timezone);
        $cacheKey = implode(':', [
            'nightscope', 'tonight', 'v19', $date->format('Y-m-d'), $location->id,
            $location->updated_at?->getTimestamp() ?? 0,
            $location->bortle_class ?? 'x',
            $setup?->id ?? 0,
            $setup?->updated_at?->getTimestamp() ?? 0,
            implode(',', $objectTypes),
            hash('sha256', $search),
            $favoriteIds === null ? 'all' : hash('sha256', implode(',', $favoriteIds)),
        ]);

        /** @var TonightData $ranked */
        $ranked = Cache::remember($cacheKey, now()->addMinutes(30), fn (): TonightData => $this->calculate(
            $date,
            $location,
            $setup,
            $objectTypes,
            $search,
            $favoriteIds,
        ));

        $targets = $ranked->targets;

        if ($neverPhotographedOnly) {
            $targets = array_values(array_filter(
                $targets,
                fn (TargetScore $score): bool => $score->neverPhotographed,
            ));
        }

        $visible = array_slice($targets, 0, $recommendationLimit);
        $max = (int) config('nightscope.tonight.recommendation_max', 48);

        return new TonightData(
            date: $ranked->date,
            location: $ranked->location,
            equipmentSetup: $ranked->equipmentSetup,
            optics: $ranked->optics,
            night: $ranked->night,
            moon: $ranked->moon,
            weather: $ranked->weather,
            nightScore: $ranked->nightScore,
            targets: $visible,
            objectTypes: $ranked->objectTypes,
            recommendationLimit: $recommendationLimit,
            hasMoreRecommendations: count($targets) > count($visible)
                && $recommendationLimit < $max,
            neverPhotographedOnly: $neverPhotographedOnly,
            search: $search,
            favoritesOnly: $favoriteIds !== null,
        );
    }

    /**
     * @param  list<string>  $objectTypes
     */
    private function calculate(
        CarbonImmutable $date,
        ObservingLocation $location,
        ?EquipmentSetup $setup,
        array $objectTypes,
        string $search,
        ?array $favoriteIds,
    ): TonightData {
        $night = $this->solar->forNight($date, $location);
        $at = $this->nightMidpoint($night, $date);
        $moon = $this->lunar->forNight($date, $location, $at);
        $optics = $this->optics($setup);
        $weather = $this->weatherScore->calculate($this->weather->forecast($location, $date, 8), $night);
        $maxRecommendations = (int) config('nightscope.tonight.recommendation_max', 48);

        return new TonightData(
            date: $date,
            location: $location,
            equipmentSetup: $setup,
            optics: $optics,
            night: $night,
            moon: $moon,
            weather: $weather,
            nightScore: $this->nightScore->calculate($night, $moon, $weather),
            targets: $this->targets($date, $location, $night, $moon, $optics, $weather, $setup, $objectTypes, $maxRecommendations, $search, $favoriteIds),
            objectTypes: $objectTypes,
            recommendationLimit: $maxRecommendations,
        );
    }

    public function target(
        CelestialObject $target,
        CarbonImmutable $date,
        ?ObservingLocation $location = null,
        ?EquipmentSetup $setup = null,
        bool $setupSpecified = false,
    ): TargetScore {
        $location ??= ObservingLocation::query()->where('is_default', true)->first()
            ?? ObservingLocation::query()->oldest('id')->first();

        if (! $location) {
            throw new DomainException(__('Aucun lieu d’observation n’est configuré.'));
        }

        if (! $setupSpecified) {
            $setup = EquipmentSetup::query()->with('enabledSetupItems')->where('is_default', true)->first()
                ?? EquipmentSetup::query()->with('enabledSetupItems')->oldest('id')->first();
        } elseif ($setup && ! $setup->relationLoaded('enabledSetupItems')) {
            $setup->load('enabledSetupItems');
        }

        $date = $date->setTimezone($location->timezone);
        $night = $this->solar->forNight($date, $location);
        $at = $this->nightMidpoint($night, $date);
        $diversity = $this->history->catalogDiversity();
        $weather = $this->weatherScore->calculate($this->weather->forecast($location, $date, 8), $night);

        return $this->targetScore->calculate(
            $target,
            $this->visibility->forNight($target, $location, $date, night: $night),
            $this->lunar->at($at, $location),
            $this->optics($setup),
            $weather,
            $this->history->analyze($target, recentLimit: 0),
            $this->equipmentScore->filterTypesFor($setup),
            $diversity['photos_by_type'],
            $diversity['total_photos'],
            $date,
            $setup,
            $location->bortle_class,
        );
    }

    private function optics(?EquipmentSetup $setup): ?EquipmentOptics
    {
        if (! $setup) {
            return null;
        }

        try {
            return $this->equipment->calculate($setup);
        } catch (DomainException) {
            return null;
        }
    }

    /** @return list<TargetScore> */
    private function targets(
        CarbonImmutable $date,
        ObservingLocation $location,
        SolarNight $night,
        MoonData $moon,
        ?EquipmentOptics $optics,
        ?WeatherScore $weather,
        ?EquipmentSetup $setup,
        array $objectTypes,
        int $recommendationLimit,
        string $search,
        ?array $favoriteIds,
    ): array {
        $window = $night->observingWindow();

        if ($window === null) {
            return [];
        }

        $candidates = $this->candidates($location, $window, $objectTypes, $search, $favoriteIds);
        $histories = $this->history->analyzeMany($candidates);
        $diversity = $this->history->catalogDiversity();
        $filterTypes = $this->equipmentScore->filterTypesFor($setup);
        $bortleClass = $location->bortle_class;

        return $candidates
            ->map(fn (CelestialObject $target): TargetScore => $this->targetScore->calculate(
                $target,
                $this->visibility->forNight($target, $location, $date, night: $night),
                $moon,
                $optics,
                $weather,
                $histories[$target->id] ?? null,
                $filterTypes,
                $diversity['photos_by_type'],
                $diversity['total_photos'],
                $date,
                $setup,
                $bortleClass,
            ))
            ->filter(fn (TargetScore $score): bool => $score->visibility->rise !== null)
            ->sortByDesc(fn (TargetScore $score): int => $score->score)
            ->take($recommendationLimit)
            ->values()
            ->all();
    }

    /**
     * Présélection rapide avant le calcul détaillé : seuls les objets qui montent
     * réellement pendant la fenêtre de cette nuit, classés par intérêt. Les objets
     * sans magnitude (grandes nébuleuses) ne sont plus écartés d'office.
     *
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $window
     * @param  list<string>  $objectTypes
     * @return Collection<int, CelestialObject>
     */
    private function candidates(ObservingLocation $location, array $window, array $objectTypes, string $search, ?array $favoriteIds): Collection
    {
        $minimumAltitude = (float) config('nightscope.tonight.candidate_min_altitude', 25);
        [$start, $end] = $window;
        $startSiderealDeg = $this->coordinates->localSiderealTimeDeg($start, $location->longitude);
        $windowSiderealDeg = $start->diffInSeconds($end) / 3600 * 15.041;

        $ranked = CelestialObject::query()
            ->select(['id', 'catalog', 'catalog_number', 'name', 'alternative_names', 'type', 'ra_deg', 'dec_deg', 'magnitude', 'angular_width_arcmin', 'angular_height_arcmin'])
            ->search($search)
            ->when($favoriteIds !== null, fn ($query) => $query->whereIn('id', $favoriteIds))
            ->whereBetween('dec_deg', [max(-90, $location->latitude - 90 + $minimumAltitude), 90])
            ->whereIn('type', $objectTypes)
            ->get()
            ->filter(fn (CelestialObject $target): bool => $this->maximumAltitudeDuring(
                $target->ra_deg, $target->dec_deg, $location->latitude, $startSiderealDeg, $windowSiderealDeg,
            ) >= $minimumAltitude)
            ->sortByDesc(fn (CelestialObject $target): float => $this->interest->calculate($target))
            ->take((int) config('nightscope.tonight.candidate_limit', 250));

        $order = $ranked->pluck('id')->flip();

        return CelestialObject::query()
            ->select([
                'id', 'catalog', 'catalog_number', 'name', 'alternative_names', 'type', 'constellation', 'ra_deg', 'dec_deg',
                'magnitude', 'angular_width_arcmin', 'angular_height_arcmin',
            ])
            ->withCount('astrophotos')
            ->whereIn('id', $order->keys())
            ->get()
            ->sortBy(fn (CelestialObject $target): int => $order[$target->id])
            ->values();
    }

    /**
     * Hauteur maximale atteinte pendant la fenêtre : au passage au méridien s'il a
     * lieu dans la fenêtre, sinon à l'une des deux bornes.
     */
    private function maximumAltitudeDuring(float $raDeg, float $decDeg, float $latitudeDeg, float $startSiderealDeg, float $windowSiderealDeg): float
    {
        $startHourAngle = fmod($startSiderealDeg - $raDeg + 540, 360) - 180;
        $endHourAngle = $startHourAngle + $windowSiderealDeg;

        if ($startHourAngle <= 0 && $endHourAngle >= 0 || $endHourAngle >= 360) {
            return 90 - abs($latitudeDeg - $decDeg);
        }

        $altitudeAt = function (float $hourAngleDeg) use ($decDeg, $latitudeDeg): float {
            $sine = sin(deg2rad($latitudeDeg)) * sin(deg2rad($decDeg))
                + cos(deg2rad($latitudeDeg)) * cos(deg2rad($decDeg)) * cos(deg2rad($hourAngleDeg));

            return rad2deg(asin(max(-1, min(1, $sine))));
        };

        return max($altitudeAt($startHourAngle), $altitudeAt($endHourAngle));
    }

    private function nightMidpoint(SolarNight $night, CarbonImmutable $date): CarbonImmutable
    {
        $window = $night->observingWindow();

        return $window === null
            ? $date->setTime(23, 0)
            : $window[0]->addSeconds((int) ($window[0]->diffInSeconds($window[1]) / 2));
    }

    /** @param  list<string>|null  $objectTypes @return list<string> */
    private function normalizeObjectTypes(?array $objectTypes): array
    {
        $allowed = CelestialObjectType::values();
        $defaults = (array) config('nightscope.tonight.default_types', $allowed);
        $types = array_values(array_intersect($objectTypes ?? $defaults, $allowed));
        sort($types);

        return $types !== [] ? $types : array_values(array_intersect($defaults, $allowed));
    }

    private function normalizeRecommendationLimit(?int $limit): int
    {
        $default = (int) config('nightscope.tonight.recommendation_limit', 12);
        $step = (int) config('nightscope.tonight.recommendation_step', 12);
        $max = (int) config('nightscope.tonight.recommendation_max', 48);
        $limit ??= $default;
        $limit = max($default, min($max, $limit));

        if ($limit === $default) {
            return $default;
        }

        $stepsAboveDefault = (int) ceil(($limit - $default) / max(1, $step));

        return min($max, $default + ($stepsAboveDefault * $step));
    }
}
