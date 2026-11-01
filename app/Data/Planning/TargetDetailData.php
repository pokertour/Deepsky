<?php

namespace App\Data\Planning;

use App\Data\Astronomy\AltitudeSample;
use App\Data\Astronomy\SolarNight;
use App\Data\Equipment\MosaicSuggestion;
use App\Enums\MosaicDisplayMode;
use App\Models\EquipmentSetup;
use App\Models\ImagingPlan;
use App\Models\ObservingLocation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final readonly class TargetDetailData
{
    /**
     * @param  list<AltitudeSample>  $moonTimeline
     * @param  list<ExternalReferencePhoto>  $externalReferencePhotos
     * @param  Collection<int, ObservingLocation>  $locations
     * @param  Collection<int, EquipmentSetup>  $equipmentSetups
     * @param  Collection<int, ImagingPlan>  $editablePlans
     */
    public function __construct(
        public CarbonImmutable $date,
        public TargetScore $result,
        public ObservingLocation $location,
        public ?EquipmentSetup $equipmentSetup,
        public SolarNight $night,
        public array $moonTimeline,
        public TargetAltitudeChart $chart,
        public ?FramingPreview $framingPreview,
        public ?string $framingUnavailableReason,
        public TargetHistory $history,
        public array $externalReferencePhotos,
        public string $astroBinSearchUrl,
        public Collection $locations,
        public Collection $equipmentSetups,
        public MosaicDisplayMode $mosaicMode = MosaicDisplayMode::Auto,
        public ?MosaicSuggestion $mosaicOverlay = null,
        public Collection $editablePlans = new Collection,
    ) {}
}
