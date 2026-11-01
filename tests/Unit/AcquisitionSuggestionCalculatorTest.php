<?php

namespace Tests\Unit;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\SetupItemSpecs;
use App\Enums\AcquisitionSuggestionSource;
use App\Enums\FilterType;
use App\Enums\SetupItemType;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use App\Services\Planning\AcquisitionSuggestionCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcquisitionSuggestionCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_prefers_camera_gain_and_typical_sub_exposure(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from([
                'preferred_gain' => 56,
                'typical_sub_exposure_seconds' => 240,
                'cooled' => true,
            ]),
        ]);
        $setup->setupItems()->attach([$camera->id => ['enabled' => true]]);
        $setup->load('enabledSetupItems');
        $target = CelestialObject::factory()->create(['magnitude' => 9.0]);

        $suggestion = $this->app->make(AcquisitionSuggestionCalculator::class)->suggest(
            $target,
            $setup,
            $this->optics(5.0),
            ['broadband'],
        );

        $this->assertNotNull($suggestion);
        $this->assertSame(56, $suggestion->gain);
        $this->assertSame(AcquisitionSuggestionSource::Preferred, $suggestion->gainSource);
        $this->assertSame(240, $suggestion->subExposureSeconds);
        $this->assertSame(AcquisitionSuggestionSource::Preferred, $suggestion->exposureSource);
    }

    public function test_uses_historical_gain_median_for_same_target(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['cooled' => true]),
        ]);
        $setup->setupItems()->attach([$camera->id => ['enabled' => true]]);
        $setup->load('enabledSetupItems');
        $target = CelestialObject::factory()->create(['magnitude' => 8.5]);

        Astrophoto::factory()->create([
            'celestial_object_id' => $target->id,
            'equipment_setup_id' => $setup->id,
            'gain' => 80,
            'captured_at' => '2026-07-01',
        ]);
        Astrophoto::factory()->create([
            'celestial_object_id' => $target->id,
            'equipment_setup_id' => $setup->id,
            'gain' => 100,
            'captured_at' => '2026-07-02',
        ]);
        Astrophoto::factory()->create([
            'celestial_object_id' => $target->id,
            'equipment_setup_id' => $setup->id,
            'gain' => 120,
            'captured_at' => '2026-07-03',
        ]);

        $suggestion = $this->app->make(AcquisitionSuggestionCalculator::class)->suggest(
            $target,
            $setup,
            $this->optics(5.0),
            ['broadband'],
        );

        $this->assertSame(100, $suggestion?->gain);
        $this->assertSame(AcquisitionSuggestionSource::Historical, $suggestion?->gainSource);
    }

    public function test_heuristic_exposure_lengthens_for_narrowband_and_slower_ratio(): void
    {
        $calculator = $this->app->make(AcquisitionSuggestionCalculator::class);
        $target = CelestialObject::factory()->create(['magnitude' => 9.0, 'type' => 'galaxy']);
        $setup = EquipmentSetup::factory()->create();
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['cooled' => true]),
        ]);
        $setup->setupItems()->attach([$camera->id => ['enabled' => true]]);
        $setup->load('enabledSetupItems');

        $broadband = $calculator->suggest($target, $setup, $this->optics(5.0), ['broadband']);
        $narrowbandTarget = CelestialObject::factory()->create(['magnitude' => 9.0, 'type' => 'nebula']);
        $narrowSetup = EquipmentSetup::factory()->create();
        $narrowCamera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['cooled' => true]),
        ]);
        $narrowFilter = SetupItem::factory()->create([
            'type' => SetupItemType::Filter,
            'name' => 'Ha 7nm',
            'specs' => SetupItemSpecs::from(['filter_type' => 'narrowband', 'bandwidth_nm' => 7]),
        ]);
        $narrowSetup->setupItems()->attach([
            $narrowCamera->id => ['enabled' => true],
            $narrowFilter->id => ['enabled' => true],
        ]);
        $narrowSetup->load('enabledSetupItems');

        $narrowband = $calculator->suggest($narrowbandTarget, $narrowSetup, $this->optics(5.0), ['narrowband']);
        $slowNarrowband = $calculator->suggest($narrowbandTarget, $narrowSetup, $this->optics(7.0), ['narrowband']);

        $this->assertSame(120, $broadband?->subExposureSeconds);
        $this->assertSame(FilterType::UvIrCut, $broadband?->primaryFilter()?->filterType);
        $this->assertTrue(($narrowband?->subExposureSeconds ?? 0) >= 300);
        $this->assertTrue(($slowNarrowband?->subExposureSeconds ?? 0) > ($narrowband?->subExposureSeconds ?? 0));
        $this->assertSame(AcquisitionSuggestionSource::Heuristic, $broadband?->exposureSource);
        $this->assertSame(100, $broadband?->gain);
    }

    public function test_exposure_follows_recommended_dual_band_even_when_uv_ir_also_enabled(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['cooled' => true]),
        ]);
        $uvIr = SetupItem::factory()->create([
            'type' => SetupItemType::Filter,
            'name' => 'UV/IR Cut',
            'specs' => SetupItemSpecs::from(['filter_type' => 'uv_ir_cut']),
        ]);
        $lUltimate = SetupItem::factory()->create([
            'type' => SetupItemType::Filter,
            'brand' => 'Optolong',
            'name' => 'L-Ultimate',
            'specs' => SetupItemSpecs::from(['filter_type' => 'dual_band', 'bandwidth_nm' => 3]),
        ]);
        $setup->setupItems()->attach([
            $camera->id => ['enabled' => true],
            $uvIr->id => ['enabled' => true],
            $lUltimate->id => ['enabled' => true],
        ]);
        $setup->load('enabledSetupItems');
        $target = CelestialObject::factory()->create(['type' => 'nebula', 'magnitude' => 9.0]);

        $suggestion = $this->app->make(AcquisitionSuggestionCalculator::class)->suggest(
            $target,
            $setup,
            $this->optics(5.0),
            ['uv_ir_cut', 'dual_band'],
            moonIlluminationPercent: 80,
            moonSeparationDeg: 25,
        );

        $this->assertSame(FilterType::DualBand, $suggestion?->primaryFilter()?->filterType);
        $this->assertSame(240, $suggestion?->subExposureSeconds);
        $this->assertStringContainsString('L-Ultimate', $suggestion?->summary ?? '');
    }

    private function optics(float $focalRatio): EquipmentOptics
    {
        $focalLength = 500.0;
        $aperture = $focalLength / $focalRatio;

        return new EquipmentOptics(
            nativeFocalLengthMm: $focalLength,
            effectiveFocalLengthMm: $focalLength,
            apertureMm: $aperture,
            focalRatio: $focalRatio,
            sensorWidthMm: 11.31,
            sensorHeightMm: 11.31,
            pixelSizeUm: 3.76,
            pixelScaleArcsec: 1.5,
            horizontalFieldOfViewDeg: 1.3,
            verticalFieldOfViewDeg: 1.3,
            diagonalFieldOfViewDeg: 1.8,
        );
    }
}
