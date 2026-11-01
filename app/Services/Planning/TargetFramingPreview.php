<?php

namespace App\Services\Planning;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\FramingResult;
use App\Data\Equipment\MosaicSuggestion;
use App\Data\Planning\FramingPreview;
use App\Models\CelestialObject;
use App\Services\Astronomy\CelestialPreview;

class TargetFramingPreview
{
    private const IMAGE_ASPECT_RATIO = 16 / 9;

    public function __construct(private CelestialPreview $preview) {}

    public function calculate(
        CelestialObject $target,
        EquipmentOptics $optics,
        ?FramingResult $framing,
        ?MosaicSuggestion $mosaicOverlay = null,
    ): FramingPreview {
        $rigHorizontalFov = $framing?->rotated
            ? $optics->verticalFieldOfViewDeg
            : $optics->horizontalFieldOfViewDeg;
        $rigVerticalFov = $framing?->rotated
            ? $optics->horizontalFieldOfViewDeg
            : $optics->verticalFieldOfViewDeg;

        $frameHorizontalFov = $rigHorizontalFov;
        $frameVerticalFov = $rigVerticalFov;

        if ($mosaicOverlay !== null && $mosaicOverlay->totalPanels() > 1) {
            $frameHorizontalFov = $mosaicOverlay->horizontalSpanDegrees($rigHorizontalFov);
            $frameVerticalFov = $mosaicOverlay->verticalSpanDegrees($rigVerticalFov);
        }

        $objectFov = max(
            (float) ($target->angular_width_arcmin ?? 0),
            (float) ($target->angular_height_arcmin ?? 0),
        ) / 60 * 1.5;
        $imageHorizontalFov = $this->preview->boundedFieldOfView(max(
            $objectFov,
            $frameHorizontalFov * 1.25,
            $frameVerticalFov * self::IMAGE_ASPECT_RATIO * 1.25,
        ));
        $imageVerticalFov = $imageHorizontalFov / self::IMAGE_ASPECT_RATIO;
        $widthPercent = $frameHorizontalFov / $imageHorizontalFov * 100;
        $heightPercent = $frameVerticalFov / $imageVerticalFov * 100;

        return new FramingPreview(
            imageUrl: $this->preview->url($target, $imageHorizontalFov),
            imageHorizontalFieldOfViewDeg: $imageHorizontalFov,
            imageVerticalFieldOfViewDeg: $imageVerticalFov,
            rigHorizontalFieldOfViewDeg: $rigHorizontalFov,
            rigVerticalFieldOfViewDeg: $rigVerticalFov,
            frameWidthPercent: $widthPercent,
            frameHeightPercent: $heightPercent,
            renderedWidthPercent: min(96, max(3, $widthPercent)),
            renderedHeightPercent: min(96, max(3, $heightPercent)),
            rotationDeg: $framing?->rotated ? 90 : 0,
            fitsInPreview: $widthPercent <= 100 && $heightPercent <= 100,
        );
    }
}
