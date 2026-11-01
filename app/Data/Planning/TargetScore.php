<?php

namespace App\Data\Planning;

use App\Data\Astronomy\TargetVisibility;
use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\FramingResult;
use App\Models\CelestialObject;

final readonly class TargetScore
{
    public function __construct(
        public CelestialObject $target,
        public int $score,
        public float $moonSeparationDeg,
        public ?float $pointingAzimuthDeg,
        public ?string $pointingDirection,
        public string $previewImageUrl,
        public ?EquipmentOptics $optics,
        public TargetVisibility $visibility,
        public ?FramingResult $framing,
        public bool $neverPhotographed,
        public ?AcquisitionSuggestion $acquisition = null,
    ) {}
}
