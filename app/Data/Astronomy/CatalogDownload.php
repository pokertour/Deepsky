<?php

namespace App\Data\Astronomy;

final readonly class CatalogDownload
{
    public function __construct(
        public string $contents,
        public string $version,
    ) {}
}
