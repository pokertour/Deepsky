<?php

namespace Tests\Unit;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\SetupItemSpecs;
use App\Enums\SetupItemType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use App\Services\Planning\EquipmentScoreCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EquipmentScoreCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_narrowband_prefers_nebulae_over_galaxies(): void
    {
        $calculator = new EquipmentScoreCalculator;
        $optics = $this->optics(focalLengthMm: 400, pixelScale: 2.0, fovDeg: 2.0);
        $nebula = new CelestialObject([
            'type' => 'nebula',
            'angular_width_arcmin' => 90,
            'angular_height_arcmin' => 70,
        ]);
        $galaxy = new CelestialObject([
            'type' => 'galaxy',
            'angular_width_arcmin' => 20,
            'angular_height_arcmin' => 10,
        ]);

        $nebulaScore = $calculator->calculate($nebula, $optics, ['narrowband']);
        $galaxyScore = $calculator->calculate($galaxy, $optics, ['narrowband']);

        $this->assertTrue($nebulaScore > $galaxyScore);
        $this->assertGreaterThan(70, $nebulaScore);
    }

    public function test_long_focal_length_suits_galaxies_better_than_wide_nebulae(): void
    {
        $calculator = new EquipmentScoreCalculator;
        $optics = $this->optics(focalLengthMm: 1200, pixelScale: 1.0, fovDeg: 0.7);
        $galaxy = new CelestialObject([
            'type' => 'galaxy',
            'angular_width_arcmin' => 15,
            'angular_height_arcmin' => 10,
        ]);
        $wideNebula = new CelestialObject([
            'type' => 'nebula',
            'angular_width_arcmin' => 120,
            'angular_height_arcmin' => 90,
        ]);

        $galaxyScore = $calculator->calculate($galaxy, $optics, ['broadband']);
        $wideNebulaScore = $calculator->calculate($wideNebula, $optics, ['broadband']);

        $this->assertTrue($galaxyScore > $wideNebulaScore);
    }

    public function test_filter_types_are_read_from_enabled_setup_items(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $filter = SetupItem::factory()->create([
            'type' => SetupItemType::Filter,
            'specs' => SetupItemSpecs::from(['filter_type' => 'dual_band', 'bandwidth_nm' => 12]),
        ]);
        $other = SetupItem::factory()->create([
            'type' => SetupItemType::Filter,
            'specs' => SetupItemSpecs::from(['filter_type' => 'broadband']),
        ]);
        $setup->setupItems()->attach([
            $filter->id => ['enabled' => true],
            $other->id => ['enabled' => false],
        ]);
        $setup->load('enabledSetupItems');

        $types = (new EquipmentScoreCalculator)->filterTypesFor($setup);

        $this->assertSame(['dual_band'], $types);
    }

    private function optics(float $focalLengthMm, float $pixelScale, float $fovDeg): EquipmentOptics
    {
        return new EquipmentOptics(
            nativeFocalLengthMm: $focalLengthMm,
            effectiveFocalLengthMm: $focalLengthMm,
            apertureMm: 100,
            focalRatio: $focalLengthMm / 100,
            sensorWidthMm: 11.31,
            sensorHeightMm: 11.31,
            pixelSizeUm: 3.76,
            pixelScaleArcsec: $pixelScale,
            horizontalFieldOfViewDeg: $fovDeg,
            verticalFieldOfViewDeg: $fovDeg,
            diagonalFieldOfViewDeg: $fovDeg * 1.4,
        );
    }
}
