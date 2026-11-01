<?php

namespace App\Data\Planning;

use App\Models\Astrophoto;
use Carbon\CarbonImmutable;

final readonly class TargetHistory
{
    /**
     * @param  list<Astrophoto>  $recentPhotos
     */
    public function __construct(
        public int $photoCount,
        public int $sessionCount,
        public int $totalIntegrationSeconds,
        public ?CarbonImmutable $lastCapturedAt,
        public array $recentPhotos = [],
    ) {}
}
