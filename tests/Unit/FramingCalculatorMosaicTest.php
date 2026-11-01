<?php

namespace Tests\Unit;

use App\Data\Equipment\EquipmentOptics;
use App\Enums\FramingRating;
use App\Services\Equipment\FramingCalculator;
use Tests\TestCase;

class FramingCalculatorMosaicTest extends TestCase
{
    public function test_it_recommends_mosaic_grid_when_object_exceeds_fov(): void
    {
        config([
            'nightscope.framing.mosaic_threshold' => 1.20,
            'nightscope.framing.mosaic_overlap' => 0.20,
            'nightscope.framing.excellent_threshold' => 0.65,
            'nightscope.framing.good_threshold' => 0.30,
        ]);

        $optics = new EquipmentOptics(
            nativeFocalLengthMm: 750,
            effectiveFocalLengthMm: 750,
            apertureMm: 150,
            focalRatio: 5,
            sensorWidthMm: 11.31,
            sensorHeightMm: 11.31,
            pixelSizeUm: 3.76,
            pixelScaleArcsec: 1.03,
            horizontalFieldOfViewDeg: 0.86,
            verticalFieldOfViewDeg: 0.86,
            diagonalFieldOfViewDeg: 1.22,
        );

        $framing = (new FramingCalculator)->calculate(120, 30, $optics);

        $this->assertSame(FramingRating::Mosaic, $framing->rating);
        $this->assertTrue($framing->mosaicRecommended);
        $this->assertNotNull($framing->mosaic);
        $this->assertGreaterThanOrEqual(2, $framing->mosaic->totalPanels());
        $this->assertSame(20.0, $framing->mosaic->overlapPercent);
        $this->assertStringContainsString('panneaux', $framing->mosaic->label());
    }

    public function test_it_does_not_suggest_mosaic_for_well_framed_targets(): void
    {
        config([
            'nightscope.framing.mosaic_threshold' => 1.20,
            'nightscope.framing.mosaic_overlap' => 0.20,
            'nightscope.framing.excellent_threshold' => 0.65,
            'nightscope.framing.good_threshold' => 0.30,
        ]);

        $optics = new EquipmentOptics(
            nativeFocalLengthMm: 250,
            effectiveFocalLengthMm: 250,
            apertureMm: 50,
            focalRatio: 5,
            sensorWidthMm: 23.5,
            sensorHeightMm: 15.6,
            pixelSizeUm: 3.76,
            pixelScaleArcsec: 3.1,
            horizontalFieldOfViewDeg: 5.4,
            verticalFieldOfViewDeg: 3.6,
            diagonalFieldOfViewDeg: 6.5,
        );

        $framing = (new FramingCalculator)->calculate(20, 15, $optics);

        $this->assertFalse($framing->mosaicRecommended);
        $this->assertNull($framing->mosaic);
        $this->assertNotSame(FramingRating::Mosaic, $framing->rating);
    }

    public function test_it_suggests_mosaic_as_soon_as_object_overflows_fov(): void
    {
        config([
            'nightscope.framing.mosaic_threshold' => 1.20,
            'nightscope.framing.mosaic_overlap' => 0.20,
            'nightscope.framing.excellent_threshold' => 0.65,
            'nightscope.framing.good_threshold' => 0.30,
        ]);

        $optics = new EquipmentOptics(
            nativeFocalLengthMm: 750,
            effectiveFocalLengthMm: 750,
            apertureMm: 150,
            focalRatio: 5,
            sensorWidthMm: 11.31,
            sensorHeightMm: 11.31,
            pixelSizeUm: 3.76,
            pixelScaleArcsec: 1.03,
            horizontalFieldOfViewDeg: 0.86,
            verticalFieldOfViewDeg: 0.86,
            diagonalFieldOfViewDeg: 1.22,
        );

        // ~1.05× le FOV : Trop grand, mais mosaïque déjà conseillée.
        $framing = (new FramingCalculator)->calculate(55, 55, $optics);

        $this->assertTrue($framing->mosaicRecommended);
        $this->assertNotNull($framing->mosaic);
        $this->assertSame(FramingRating::TooLarge, $framing->rating);
    }
}
