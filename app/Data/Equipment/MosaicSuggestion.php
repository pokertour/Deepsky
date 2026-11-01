<?php

namespace App\Data\Equipment;

final readonly class MosaicSuggestion
{
    public function __construct(
        public int $columns,
        public int $rows,
        public float $overlapPercent,
        public bool $rotated,
    ) {}

    public function totalPanels(): int
    {
        return $this->columns * $this->rows;
    }

    public function label(): string
    {
        return $this->columns.'×'.$this->rows.' ('.$this->totalPanels().' panneaux)';
    }

    public function spanDegrees(float $panelFovDeg, int $panels): float
    {
        if ($panels <= 1) {
            return $panelFovDeg;
        }

        $overlap = max(0.0, min(0.4, $this->overlapPercent / 100));

        return $panelFovDeg * (($panels - 1) * (1 - $overlap) + 1);
    }

    public function horizontalSpanDegrees(float $panelHorizontalFovDeg): float
    {
        return $this->spanDegrees($panelHorizontalFovDeg, $this->columns);
    }

    public function verticalSpanDegrees(float $panelVerticalFovDeg): float
    {
        return $this->spanDegrees($panelVerticalFovDeg, $this->rows);
    }
}
