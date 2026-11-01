<?php

namespace Tests\Unit;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\FramingResult;
use App\Data\Equipment\MosaicSuggestion;
use App\Enums\FramingRating;
use App\Models\CelestialObject;
use App\Services\Planning\TargetFramingPreview;
use Tests\TestCase;

class TargetFramingPreviewTest extends TestCase
{
    public function test_it_places_the_rotated_rig_field_inside_the_sky_preview(): void
    {
        $target = new CelestialObject([
            'ra_deg' => 10,
            'dec_deg' => -20,
            'angular_width_arcmin' => 60,
            'angular_height_arcmin' => 30,
        ]);
        $optics = new EquipmentOptics(750, 750, 150, 5, 20, 10, 3.76, 1.03, 1.5, 0.75, 1.68);
        $framing = new FramingResult(FramingRating::Good, 70, 80, 70, true, false, null);

        $preview = $this->app->make(TargetFramingPreview::class)->calculate($target, $optics, $framing);

        $this->assertSame(90, $preview->rotationDeg);
        $this->assertEqualsWithDelta(0.75, $preview->rigHorizontalFieldOfViewDeg, 0.001);
        $this->assertEqualsWithDelta(1.5, $preview->rigVerticalFieldOfViewDeg, 0.001);
        $this->assertTrue($preview->fitsInPreview);
        $this->assertStringContainsString('/nightscope/preview', $preview->imageUrl);
        $this->assertStringContainsString('ra=10', $preview->imageUrl);
    }

    public function test_it_expands_frame_to_mosaic_span_when_overlay_is_present(): void
    {
        $target = new CelestialObject([
            'ra_deg' => 10,
            'dec_deg' => -20,
            'angular_width_arcmin' => 20,
            'angular_height_arcmin' => 20,
        ]);
        $optics = new EquipmentOptics(750, 750, 150, 5, 20, 10, 3.76, 1.03, 1.5, 0.75, 1.68);
        $framing = new FramingResult(FramingRating::Good, 40, 40, 30, false, false, null);
        $mosaic = new MosaicSuggestion(2, 2, 20, false);

        $preview = $this->app->make(TargetFramingPreview::class)
            ->calculate($target, $optics, $framing, $mosaic);

        $expectedHorizontal = 1.5 * ((2 - 1) * 0.8 + 1);
        $expectedVertical = 0.75 * ((2 - 1) * 0.8 + 1);

        $this->assertEqualsWithDelta(1.5, $preview->rigHorizontalFieldOfViewDeg, 0.001);
        $this->assertEqualsWithDelta(0.75, $preview->rigVerticalFieldOfViewDeg, 0.001);
        $this->assertGreaterThan($preview->rigHorizontalFieldOfViewDeg, $preview->imageHorizontalFieldOfViewDeg);
        $this->assertEqualsWithDelta(
            $expectedHorizontal / $preview->imageHorizontalFieldOfViewDeg * 100,
            $preview->frameWidthPercent,
            0.1,
        );
        $this->assertEqualsWithDelta(
            $expectedVertical / $preview->imageVerticalFieldOfViewDeg * 100,
            $preview->frameHeightPercent,
            0.1,
        );
    }
}
