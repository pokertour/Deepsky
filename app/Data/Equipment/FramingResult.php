<?php

namespace App\Data\Equipment;

use App\Enums\FramingRating;

final readonly class FramingResult
{
    public function __construct(
        public FramingRating $rating,
        public float $frameFillPercent,
        public float $horizontalCoveragePercent,
        public float $verticalCoveragePercent,
        public bool $rotated,
        public bool $mosaicRecommended,
        public ?MosaicSuggestion $mosaic = null,
    ) {}

    public function mosaicSuggestion(): ?MosaicSuggestion
    {
        return isset($this->mosaic) ? $this->mosaic : null;
    }
}
