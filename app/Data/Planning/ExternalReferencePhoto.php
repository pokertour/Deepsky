<?php

namespace App\Data\Planning;

final readonly class ExternalReferencePhoto
{
    public function __construct(
        public string $title,
        public string $author,
        public string $thumbUrl,
        public string $pageUrl,
        public string $matchLabel,
        public int $similarityScore,
        public ?float $focalLengthMm = null,
    ) {}
}
