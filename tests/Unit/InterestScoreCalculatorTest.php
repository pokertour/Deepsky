<?php

namespace Tests\Unit;

use App\Models\CelestialObject;
use App\Services\Planning\InterestScoreCalculator;
use Tests\TestCase;

class InterestScoreCalculatorTest extends TestCase
{
    private function score(array $attributes): float
    {
        return app(InterestScoreCalculator::class)->calculate(new CelestialObject([
            'catalog' => 'NGC',
            'name' => null,
            'alternative_names' => [],
            'magnitude' => null,
            'angular_width_arcmin' => null,
            'angular_height_arcmin' => null,
            ...$attributes,
        ]));
    }

    public function test_showpieces_outrank_small_anonymous_objects(): void
    {
        $andromeda = $this->score(['catalog' => 'M', 'name' => 'Andromeda Galaxy', 'magnitude' => 3.44, 'angular_width_arcmin' => 177.8, 'angular_height_arcmin' => 69.7]);
        $northAmerica = $this->score(['name' => 'North America Nebula', 'alternative_names' => ['C 20', 'LBN 373'], 'magnitude' => 4, 'angular_width_arcmin' => 120]);
        $anonymousCluster = $this->score(['magnitude' => 7.2, 'angular_width_arcmin' => 9]);

        $this->assertGreaterThan(95, $andromeda);
        $this->assertGreaterThan(85, $northAmerica);
        $this->assertLessThan(40, $anonymousCluster);
    }

    public function test_large_nebulae_without_magnitude_are_not_penalised(): void
    {
        $rosette = $this->score(['name' => 'Rosette A', 'angular_width_arcmin' => 80, 'angular_height_arcmin' => 50]);
        $faintGalaxy = $this->score(['magnitude' => 13.5, 'angular_width_arcmin' => 1.2]);

        $this->assertGreaterThan(65, $rosette);
        $this->assertLessThan(10, $faintGalaxy);
    }

    public function test_nebula_catalogue_aliases_count_as_notoriety(): void
    {
        $catalogued = $this->score(['alternative_names' => ['LBN 616'], 'angular_width_arcmin' => 35]);
        $anonymous = $this->score(['angular_width_arcmin' => 35]);

        $this->assertGreaterThan($anonymous + 20, $catalogued);
    }
}
