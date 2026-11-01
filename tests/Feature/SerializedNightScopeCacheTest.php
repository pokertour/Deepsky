<?php

namespace Tests\Feature;

use App\Contracts\Weather\WeatherProvider;
use App\Data\Equipment\SetupItemSpecs;
use App\Data\Weather\WeatherForecast;
use App\Data\Weather\WeatherPoint;
use App\Enums\SetupItemType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Models\SetupItem;
use App\Services\Planning\TonightService;
use App\Services\Weather\WeatherManager;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SerializedNightScopeCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);
    }

    public function test_tonight_restores_nested_objects_from_serialized_cache(): void
    {
        $location = ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $target = CelestialObject::factory()->create([
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
        ]);
        $rig = EquipmentSetup::factory()->default()->create();
        $tube = SetupItem::factory()->create([
            'type' => SetupItemType::OpticalTube,
            'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
        ]);
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['sensor_width_mm' => 11.31, 'sensor_height_mm' => 11.31, 'pixel_size_um' => 3.76]),
        ]);
        $rig->setupItems()->attach([$tube->id => ['enabled' => true], $camera->id => ['enabled' => true]]);
        $service = $this->app->make(TonightService::class);
        $date = CarbonImmutable::parse('2026-08-03');

        $first = $service->build($date);
        $cached = $service->build($date);

        $this->assertCount(1, $cached->targets);
        $this->assertNotSame($first->targets[0]->target, $cached->targets[0]->target);
        $this->assertSame($target->id, $cached->targets[0]->target->id);
        $this->assertSame($location->id, $cached->location->id);
        $this->assertTrue($first->night->astronomicalNightStart->equalTo($cached->night->astronomicalNightStart));
        $this->assertCount(2, $cached->equipmentSetup->enabledSetupItems);
        $this->assertSame(750.0, $cached->optics->effectiveFocalLengthMm);
        $this->assertNotNull($cached->targets[0]->framing);
        $this->assertNotNull($cached->targets[0]->acquisition);
        $this->assertSame($first->targets[0]->score, $cached->targets[0]->score);
    }

    public function test_weather_restores_forecast_points_without_calling_the_provider_again(): void
    {
        $location = ObservingLocation::factory()->create();
        $date = CarbonImmutable::parse('2026-08-03 23:00', 'Europe/Paris');
        $forecast = new WeatherForecast('test', $date, [
            new WeatherPoint($date, 12, 4, 7, 10, 72, 14, 9, 8, 15, 2, 28000),
        ]);
        $provider = $this->mock(WeatherProvider::class);
        $provider->shouldReceive('forecast')->once()->andReturn($forecast);
        $service = $this->app->make(WeatherManager::class);

        $service->forecast($location, $date);
        $cached = $service->forecast($location, $date);

        $this->assertNotSame($forecast, $cached);
        $this->assertSame('test', $cached->provider);
        $this->assertCount(1, $cached->points);
        $this->assertSame(12.0, $cached->points[0]->cloudCoverPercent);
        $this->assertSame(9.0, $cached->points[0]->dewPointCelsius);
        $this->assertTrue($date->equalTo($cached->points[0]->at));
        $this->assertTrue($date->equalTo($cached->generatedAt));
    }
}
