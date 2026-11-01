<?php

namespace Tests\Unit;

use App\Data\Equipment\SetupItemSpecs;
use App\Enums\FramingRating;
use App\Enums\SetupItemType;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use App\Services\Equipment\EquipmentCalculator;
use App\Services\Equipment\FramingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_calculates_effective_optics_and_framing(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $tube = SetupItem::factory()->create([
            'type' => SetupItemType::OpticalTube,
            'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
        ]);
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from([
                'sensor_width_mm' => 11.31,
                'sensor_height_mm' => 11.31,
                'pixel_size_um' => 3.76,
            ]),
        ]);
        $corrector = SetupItem::factory()->create([
            'type' => SetupItemType::Corrector,
            'specs' => SetupItemSpecs::from(['factor' => 0.95]),
        ]);
        $setup->setupItems()->attach([
            $tube->id => ['enabled' => true],
            $camera->id => ['enabled' => true],
            $corrector->id => ['enabled' => true],
        ]);

        $optics = $this->app->make(EquipmentCalculator::class)->calculate($setup);
        $framing = $this->app->make(FramingCalculator::class)->calculate(120, 30, $optics);

        $this->assertEqualsWithDelta(712.5, $optics->effectiveFocalLengthMm, 0.001);
        $this->assertEqualsWithDelta(4.75, $optics->focalRatio, 0.001);
        $this->assertEqualsWithDelta(1.0886, $optics->pixelScaleArcsec, 0.001);
        $this->assertEqualsWithDelta(0.9095, $optics->horizontalFieldOfViewDeg, 0.001);
        $this->assertSame(FramingRating::Mosaic, $framing->rating);
        $this->assertTrue($framing->mosaicRecommended);
        $this->assertNotNull($framing->mosaic);
        $this->assertGreaterThanOrEqual(2, $framing->mosaic->totalPanels());
    }

    public function test_disabled_reducer_does_not_change_focal_length(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $items = collect([
            SetupItem::factory()->create([
                'type' => SetupItemType::OpticalTube,
                'specs' => SetupItemSpecs::from(['focal_length_mm' => 500, 'aperture_mm' => 100]),
            ]),
            SetupItem::factory()->create([
                'type' => SetupItemType::Camera,
                'specs' => SetupItemSpecs::from(['sensor_width_mm' => 10, 'sensor_height_mm' => 8, 'pixel_size_um' => 4]),
            ]),
            SetupItem::factory()->create([
                'type' => SetupItemType::Reducer,
                'specs' => SetupItemSpecs::from(['factor' => 0.8]),
            ]),
        ]);
        $setup->setupItems()->attach($items->mapWithKeys(
            fn (SetupItem $item, int $index): array => [$item->id => ['enabled' => $index !== 2]],
        ));

        $optics = $this->app->make(EquipmentCalculator::class)->calculate($setup);

        $this->assertSame(500.0, $optics->effectiveFocalLengthMm);
    }

    public function test_sensor_dimensions_are_derived_from_resolution_and_pixel_size(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $tube = SetupItem::factory()->create([
            'type' => SetupItemType::OpticalTube,
            'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
        ]);
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from([
                'resolution_x' => 3008,
                'resolution_y' => 3008,
                'pixel_size_um' => 3.76,
            ]),
        ]);
        $setup->setupItems()->attach([
            $tube->id => ['enabled' => true],
            $camera->id => ['enabled' => true],
        ]);

        $optics = $this->app->make(EquipmentCalculator::class)->calculate($setup);

        $this->assertEqualsWithDelta(11.31008, $optics->sensorWidthMm, 0.00001);
        $this->assertEqualsWithDelta(11.31008, $optics->sensorHeightMm, 0.00001);
        $this->assertEqualsWithDelta(0.864, $optics->horizontalFieldOfViewDeg, 0.01);
    }
}
