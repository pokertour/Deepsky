<?php

namespace App\Data\Planning;

use App\Enums\AcquisitionSuggestionSource;

final readonly class AcquisitionSuggestion
{
    /**
     * @param  list<FilterRecommendation>  $filters
     */
    public function __construct(
        public ?int $gain,
        public ?int $subExposureSeconds,
        public AcquisitionSuggestionSource $gainSource,
        public AcquisitionSuggestionSource $exposureSource,
        public string $summary,
        public array $filters = [],
    ) {}

    public function primaryFilter(): ?FilterRecommendation
    {
        return $this->filters[0] ?? null;
    }

    public function formattedSubExposure(): ?string
    {
        if ($this->subExposureSeconds === null) {
            return null;
        }

        if ($this->subExposureSeconds < 60) {
            return $this->subExposureSeconds.' s';
        }

        $minutes = intdiv($this->subExposureSeconds, 60);
        $seconds = $this->subExposureSeconds % 60;
        $human = $seconds === 0
            ? $minutes.' min'
            : $minutes.' min '.$seconds.' s';

        return $human.' · '.$this->subExposureSeconds.' s';
    }
}
