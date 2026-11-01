<?php

namespace App\Data\Planning;

use App\Enums\FilterType;

final readonly class FilterRecommendation
{
    public function __construct(
        public FilterType $filterType,
        public string $label,
        public string $reason,
        public bool $inInventory,
        public bool $onRig = false,
        public ?float $bandwidthNm = null,
        public ?string $brand = null,
        public ?string $itemName = null,
    ) {}

    public function displayName(): string
    {
        if ($this->brand || $this->itemName) {
            return trim(($this->brand ? $this->brand.' ' : '').($this->itemName ?? ''));
        }

        return $this->label;
    }

    public function availabilityLabel(): ?string
    {
        return match (true) {
            $this->onRig => __('Sur le Rig'),
            $this->inInventory => __('Dans ton inventaire · à monter'),
            default => null,
        };
    }
}
