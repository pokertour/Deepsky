<?php

namespace App\Services\Equipment;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\FramingResult;
use App\Data\Equipment\MosaicSuggestion;
use App\Enums\FramingRating;
use InvalidArgumentException;

class FramingCalculator
{
    public function calculate(
        float $objectWidthArcmin,
        float $objectHeightArcmin,
        EquipmentOptics $optics,
    ): FramingResult {
        if ($objectWidthArcmin <= 0 || $objectHeightArcmin <= 0) {
            throw new InvalidArgumentException('Les dimensions de la cible doivent être positives.');
        }

        $normal = $this->coverage($objectWidthArcmin, $objectHeightArcmin, $optics);
        $rotated = $this->coverage($objectHeightArcmin, $objectWidthArcmin, $optics);
        $useRotated = max($rotated) < max($normal);
        [$horizontal, $vertical] = $useRotated ? $rotated : $normal;
        $fill = max($horizontal, $vertical);
        $mosaicThreshold = (float) config('nightscope.framing.mosaic_threshold', 1.2);
        $fitsInSingleFrame = $fill <= 1;
        $mosaicRecommended = ! $fitsInSingleFrame;
        $mosaic = $mosaicRecommended
            ? $this->mosaicSuggestion($objectWidthArcmin, $objectHeightArcmin, $optics, $useRotated)
            : null;

        return new FramingResult(
            rating: $this->rating($fill, $mosaicThreshold),
            frameFillPercent: round($fill * 100, 1),
            horizontalCoveragePercent: round($horizontal * 100, 1),
            verticalCoveragePercent: round($vertical * 100, 1),
            rotated: $useRotated,
            mosaicRecommended: $mosaicRecommended,
            mosaic: $mosaic,
        );
    }

    /** @return array{float, float} */
    private function coverage(float $widthArcmin, float $heightArcmin, EquipmentOptics $optics): array
    {
        return [
            $widthArcmin / ($optics->horizontalFieldOfViewDeg * 60),
            $heightArcmin / ($optics->verticalFieldOfViewDeg * 60),
        ];
    }

    public function fixedMosaic(int $columns, int $rows, bool $rotated): MosaicSuggestion
    {
        $overlap = (float) config('nightscope.framing.mosaic_overlap', 0.20);
        $overlap = max(0.05, min(0.4, $overlap));

        return new MosaicSuggestion(
            columns: max(1, min(6, $columns)),
            rows: max(1, min(6, $rows)),
            overlapPercent: round($overlap * 100, 0),
            rotated: $rotated,
        );
    }

    private function mosaicSuggestion(
        float $objectWidthArcmin,
        float $objectHeightArcmin,
        EquipmentOptics $optics,
        bool $preferRotated,
    ): MosaicSuggestion {
        $overlap = (float) config('nightscope.framing.mosaic_overlap', 0.20);
        $overlap = max(0.05, min(0.4, $overlap));

        $candidates = [
            $this->panelsForOrientation($objectWidthArcmin, $objectHeightArcmin, $optics, false, $overlap),
            $this->panelsForOrientation($objectWidthArcmin, $objectHeightArcmin, $optics, true, $overlap),
        ];

        usort($candidates, function (MosaicSuggestion $left, MosaicSuggestion $right) use ($preferRotated): int {
            $total = $left->totalPanels() <=> $right->totalPanels();
            if ($total !== 0) {
                return $total;
            }

            if ($left->rotated === $preferRotated) {
                return -1;
            }

            if ($right->rotated === $preferRotated) {
                return 1;
            }

            return 0;
        });

        return $candidates[0];
    }

    private function panelsForOrientation(
        float $objectWidthArcmin,
        float $objectHeightArcmin,
        EquipmentOptics $optics,
        bool $rotated,
        float $overlap,
    ): MosaicSuggestion {
        $fovWidthArcmin = ($rotated ? $optics->verticalFieldOfViewDeg : $optics->horizontalFieldOfViewDeg) * 60;
        $fovHeightArcmin = ($rotated ? $optics->horizontalFieldOfViewDeg : $optics->verticalFieldOfViewDeg) * 60;
        $stepWidth = $fovWidthArcmin * (1 - $overlap);
        $stepHeight = $fovHeightArcmin * (1 - $overlap);

        return new MosaicSuggestion(
            columns: max(1, (int) ceil($objectWidthArcmin / max($stepWidth, 0.001))),
            rows: max(1, (int) ceil($objectHeightArcmin / max($stepHeight, 0.001))),
            overlapPercent: round($overlap * 100, 0),
            rotated: $rotated,
        );
    }

    private function rating(float $fill, float $mosaicThreshold): FramingRating
    {
        $excellent = (float) config('nightscope.framing.excellent_threshold', 0.65);
        $good = (float) config('nightscope.framing.good_threshold', 0.3);

        return match (true) {
            $fill > $mosaicThreshold => FramingRating::Mosaic,
            $fill > 1 => FramingRating::TooLarge,
            $fill >= $excellent => FramingRating::Excellent,
            $fill >= $good => FramingRating::Good,
            default => FramingRating::Small,
        };
    }
}
