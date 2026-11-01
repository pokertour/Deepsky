<?php

namespace App\Services\Equipment;

use App\Data\Equipment\FramingResult;
use App\Data\Equipment\MosaicSuggestion;
use App\Enums\MosaicDisplayMode;

class MosaicDisplayResolver
{
    public function __construct(private FramingCalculator $framing) {}

    public function resolve(MosaicDisplayMode $mode, ?FramingResult $framing): ?MosaicSuggestion
    {
        if ($mode === MosaicDisplayMode::Off || $framing === null) {
            return null;
        }

        if ($mode === MosaicDisplayMode::Auto) {
            $suggestion = $framing->mosaicSuggestion();

            return $suggestion !== null && $suggestion->totalPanels() > 1
                ? $suggestion
                : null;
        }

        $grid = $mode->grid();

        if ($grid === null) {
            return null;
        }

        [$columns, $rows] = $grid;

        return $this->framing->fixedMosaic($columns, $rows, $framing->rotated);
    }
}
