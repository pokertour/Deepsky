<?php

namespace Tests\Unit;

use App\Data\Equipment\SetupItemSpecs;
use App\Enums\FilterType;
use App\Enums\SetupItemType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use App\Services\Planning\FilterRecommendationCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilterRecommendationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_nebula_under_moon_prefers_dual_band_like_l_ultimate(): void
    {
        $setup = $this->setupWithFilters([
            ['name' => 'UV/IR Cut', 'type' => 'uv_ir_cut'],
            ['name' => 'L-Ultimate', 'type' => 'dual_band', 'bandwidth_nm' => 3.0],
        ]);
        $target = CelestialObject::factory()->create(['type' => 'nebula']);

        $recommendations = $this->app->make(FilterRecommendationCalculator::class)
            ->recommend($target, $setup, moonIlluminationPercent: 70, moonSeparationDeg: 30);

        $this->assertNotEmpty($recommendations);
        $this->assertSame(FilterType::DualBand, $recommendations[0]->filterType);
        $this->assertTrue($recommendations[0]->inInventory);
        $this->assertTrue($recommendations[0]->onRig);
        $this->assertStringContainsString('L-Ultimate', $recommendations[0]->displayName());
        $this->assertStringContainsString('Lune', $recommendations[0]->reason);
    }

    public function test_galaxy_prefers_uv_ir_cut(): void
    {
        $setup = $this->setupWithFilters([
            ['name' => 'UV/IR Cut', 'type' => 'uv_ir_cut'],
            ['name' => 'L-Ultimate', 'type' => 'dual_band'],
        ]);
        $target = CelestialObject::factory()->create(['type' => 'galaxy']);

        $recommendations = $this->app->make(FilterRecommendationCalculator::class)
            ->recommend($target, $setup);

        $this->assertSame(FilterType::UvIrCut, $recommendations[0]->filterType);
        $this->assertTrue($recommendations[0]->inInventory);
        $this->assertTrue($recommendations[0]->onRig);
        $this->assertSame(FilterType::DualBand, $recommendations[1]->filterType);
        $this->assertStringContainsString('Peu adapté', $recommendations[1]->reason);
    }

    public function test_falls_back_to_product_hint_when_filter_missing_from_rig(): void
    {
        $setup = EquipmentSetup::factory()->create();
        $setup->load('enabledSetupItems');
        $target = CelestialObject::factory()->create(['type' => 'nebula']);

        $recommendations = $this->app->make(FilterRecommendationCalculator::class)
            ->recommend($target, $setup);

        $this->assertFalse($recommendations[0]->inInventory);
        $this->assertFalse($recommendations[0]->onRig);
        $this->assertSame('L-Ultimate', $recommendations[0]->label);
        $this->assertSame(FilterType::DualBand, $recommendations[0]->filterType);
    }

    public function test_matches_inventory_filter_not_mounted_on_rig(): void
    {
        SetupItem::factory()->create([
            'type' => SetupItemType::Filter,
            'brand' => 'Optolong',
            'name' => 'L-Ultimate',
            'specs' => SetupItemSpecs::from(['filter_type' => 'dual_band', 'bandwidth_nm' => 3]),
        ]);
        $setup = EquipmentSetup::factory()->create();
        $setup->load('enabledSetupItems');
        $target = CelestialObject::factory()->create(['type' => 'nebula']);

        $recommendations = $this->app->make(FilterRecommendationCalculator::class)
            ->recommend($target, $setup);

        $this->assertTrue($recommendations[0]->inInventory);
        $this->assertFalse($recommendations[0]->onRig);
        $this->assertStringContainsString('inventaire', $recommendations[0]->reason);
        $this->assertSame('Dans ton inventaire · à monter', $recommendations[0]->availabilityLabel());
    }

    public function test_light_polluted_bortle_pushes_nebula_to_dual_band(): void
    {
        $setup = $this->setupWithFilters([
            ['name' => 'UV/IR Cut', 'type' => 'uv_ir_cut'],
            ['name' => 'L-Ultimate', 'type' => 'dual_band'],
        ]);
        $target = CelestialObject::factory()->create(['type' => 'nebula']);

        $recommendations = $this->app->make(FilterRecommendationCalculator::class)
            ->recommend($target, $setup, moonIlluminationPercent: 10, moonSeparationDeg: 120, bortleClass: 7);

        $this->assertSame(FilterType::DualBand, $recommendations[0]->filterType);
        $this->assertStringContainsString('Bortle 7', $recommendations[0]->reason);
    }

    /**
     * @param  list<array{name: string, type: string, bandwidth_nm?: float}>  $filters
     */
    private function setupWithFilters(array $filters): EquipmentSetup
    {
        $setup = EquipmentSetup::factory()->create();
        $ids = [];

        foreach ($filters as $filter) {
            $item = SetupItem::factory()->create([
                'type' => SetupItemType::Filter,
                'brand' => 'Optolong',
                'name' => $filter['name'],
                'specs' => SetupItemSpecs::from([
                    'filter_type' => $filter['type'],
                    'bandwidth_nm' => $filter['bandwidth_nm'] ?? null,
                ]),
            ]);
            $ids[$item->id] = ['enabled' => true];
        }

        $setup->setupItems()->attach($ids);
        $setup->load('enabledSetupItems');

        return $setup;
    }
}
