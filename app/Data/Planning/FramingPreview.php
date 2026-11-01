<?php

namespace App\Data\Planning;

final readonly class FramingPreview
{
    public function __construct(
        public string $imageUrl,
        public float $imageHorizontalFieldOfViewDeg,
        public float $imageVerticalFieldOfViewDeg,
        public float $rigHorizontalFieldOfViewDeg,
        public float $rigVerticalFieldOfViewDeg,
        public float $frameWidthPercent,
        public float $frameHeightPercent,
        public float $renderedWidthPercent,
        public float $renderedHeightPercent,
        public int $rotationDeg,
        public bool $fitsInPreview,
    ) {}
}
