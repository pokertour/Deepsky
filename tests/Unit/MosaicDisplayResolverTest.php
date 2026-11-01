<?php

namespace Tests\Unit;

use App\Data\Equipment\FramingResult;
use App\Data\Equipment\MosaicSuggestion;
use App\Enums\FramingRating;
use App\Enums\MosaicDisplayMode;
use App\Services\Equipment\FramingCalculator;
use App\Services\Equipment\MosaicDisplayResolver;
use Tests\TestCase;

class MosaicDisplayResolverTest extends TestCase
{
    public function test_auto_keeps_recommended_mosaic(): void
    {
        $framing = new FramingResult(
            FramingRating::Mosaic,
            180,
            180,
            120,
            false,
            true,
            new MosaicSuggestion(3, 2, 20, false),
        );

        $overlay = $this->app->make(MosaicDisplayResolver::class)
            ->resolve(MosaicDisplayMode::Auto, $framing);

        $this->assertNotNull($overlay);
        $this->assertSame(3, $overlay->columns);
        $this->assertSame(2, $overlay->rows);
    }

    public function test_off_hides_recommended_mosaic(): void
    {
        $framing = new FramingResult(
            FramingRating::Mosaic,
            180,
            180,
            120,
            false,
            true,
            new MosaicSuggestion(3, 2, 20, false),
        );

        $overlay = $this->app->make(MosaicDisplayResolver::class)
            ->resolve(MosaicDisplayMode::Off, $framing);

        $this->assertNull($overlay);
    }

    public function test_custom_grid_forces_mosaic_even_when_not_recommended(): void
    {
        config([
            'nightscope.framing.mosaic_overlap' => 0.20,
        ]);

        $framing = new FramingResult(
            FramingRating::Good,
            55,
            55,
            40,
            true,
            false,
            null,
        );

        $overlay = $this->app->make(MosaicDisplayResolver::class)
            ->resolve(MosaicDisplayMode::Grid2x2, $framing);

        $this->assertNotNull($overlay);
        $this->assertSame(2, $overlay->columns);
        $this->assertSame(2, $overlay->rows);
        $this->assertTrue($overlay->rotated);
        $this->assertSame(20.0, $overlay->overlapPercent);
    }

    public function test_fixed_mosaic_helper_clamps_panel_counts(): void
    {
        $mosaic = $this->app->make(FramingCalculator::class)->fixedMosaic(99, 0, false);

        $this->assertSame(6, $mosaic->columns);
        $this->assertSame(1, $mosaic->rows);
    }
}
