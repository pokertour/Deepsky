<?php

namespace App\Services\Planning;

use App\Data\Astronomy\AltitudeSample;
use App\Data\Astronomy\VisibilityPoint;
use App\Data\Planning\TargetDetailData;
use App\Enums\ImagingPlanStatus;
use App\Enums\MosaicDisplayMode;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ImagingPlan;
use App\Models\ObservingLocation;
use App\Services\Astronomy\LunarCalculator;
use App\Services\Astronomy\SolarCalculator;
use App\Services\Equipment\EquipmentCalculator;
use App\Services\Equipment\MosaicDisplayResolver;
use Carbon\CarbonImmutable;
use DomainException;

class TargetDetailService
{
    public function __construct(
        private TonightService $tonight,
        private TargetFramingPreview $framingPreview,
        private HistoricalTargetAnalyzer $history,
        private ExternalReferencePhotosFinder $externalReferences,
        private EquipmentCalculator $equipment,
        private SolarCalculator $solar,
        private LunarCalculator $lunar,
        private TargetAltitudeChartBuilder $chart,
        private MosaicDisplayResolver $mosaicDisplay,
    ) {}

    public function build(
        CelestialObject $target,
        CarbonImmutable $date,
        ?ObservingLocation $location = null,
        ?EquipmentSetup $equipmentSetup = null,
        bool $equipmentSetupSpecified = false,
        MosaicDisplayMode $mosaicMode = MosaicDisplayMode::Auto,
    ): TargetDetailData {
        $location ??= ObservingLocation::query()->where('is_default', true)->first()
            ?? ObservingLocation::query()->oldest('id')->first();

        if (! $location) {
            throw new DomainException(__('Aucun lieu d’observation n’est configuré.'));
        }

        if (! $equipmentSetupSpecified) {
            $equipmentSetup = EquipmentSetup::query()
                ->with('enabledSetupItems')
                ->where('is_default', true)
                ->first() ?? EquipmentSetup::query()->with('enabledSetupItems')->oldest('id')->first();
        } elseif ($equipmentSetup && ! $equipmentSetup->relationLoaded('enabledSetupItems')) {
            $equipmentSetup->load('enabledSetupItems');
        }

        $date = $date->setTimezone($location->timezone);
        $result = $this->tonight->target($target, $date, $location, $equipmentSetup, $equipmentSetupSpecified);
        $night = $this->solar->forNight($date, $location);
        $moonTimeline = $this->moonTimeline($result->visibility->timeline, $location);
        $framingUnavailableReason = null;
        $mosaicOverlay = $this->mosaicDisplay->resolve($mosaicMode, $result->framing);

        if (! $result->optics) {
            try {
                $equipmentSetup
                    ? $this->equipment->calculate($equipmentSetup)
                    : throw new DomainException(__('Aucun Rig n’est configuré.'));
            } catch (DomainException $exception) {
                $framingUnavailableReason = $exception->getMessage();
            }
        }

        return new TargetDetailData(
            date: $date,
            result: $result,
            location: $location,
            equipmentSetup: $equipmentSetup,
            night: $night,
            moonTimeline: $moonTimeline,
            chart: $this->chart->build($result->visibility, $moonTimeline),
            framingPreview: $result->optics
                ? $this->framingPreview->calculate($target, $result->optics, $result->framing, $mosaicOverlay)
                : null,
            framingUnavailableReason: $framingUnavailableReason,
            history: $this->history->analyze($target),
            externalReferencePhotos: $this->externalReferences->find($target),
            astroBinSearchUrl: $this->externalReferences->astroBinSearchUrl($target, $result->optics, $equipmentSetup),
            locations: ObservingLocation::query()->orderByDesc('is_default')->orderBy('name')->get(),
            equipmentSetups: EquipmentSetup::query()->orderByDesc('is_default')->orderBy('name')->get(),
            mosaicMode: $mosaicMode,
            mosaicOverlay: $mosaicOverlay,
            editablePlans: ImagingPlan::query()
                ->withCount('targets')
                ->whereDate('date', $date->format('Y-m-d'))
                ->where('status', ImagingPlanStatus::Planned)
                ->latest('id')
                ->get(),
        );
    }

    /**
     * @param  list<VisibilityPoint>  $timeline
     * @return list<AltitudeSample>
     */
    private function moonTimeline(array $timeline, ObservingLocation $location): array
    {
        return array_map(
            fn (VisibilityPoint $point): AltitudeSample => new AltitudeSample(
                at: $point->at,
                altitudeDeg: $this->lunar->at($point->at, $location)->altitudeDeg,
            ),
            $timeline,
        );
    }
}
