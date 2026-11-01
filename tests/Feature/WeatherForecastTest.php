<?php

namespace Tests\Feature;

use App\Contracts\Weather\WeatherProvider;
use App\Data\Weather\WeatherForecast;
use App\Data\Weather\WeatherPoint;
use App\Models\ObservingLocation;
use App\Models\User;
use App\Services\Weather\OpenMeteoWeatherProvider;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WeatherForecastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    public function test_open_meteo_provider_maps_hourly_forecast(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2026-08-03T23:00'],
                    'cloud_cover' => [12],
                    'cloud_cover_low' => [4],
                    'cloud_cover_mid' => [7],
                    'cloud_cover_high' => [10],
                    'relative_humidity_2m' => [72],
                    'temperature_2m' => [14],
                    'dew_point_2m' => [9],
                    'wind_speed_10m' => [8],
                    'wind_gusts_10m' => [15],
                    'precipitation_probability' => [2],
                    'visibility' => [28000],
                ],
            ]),
        ]);
        $location = ObservingLocation::factory()->create();

        $forecast = (new OpenMeteoWeatherProvider)->forecast($location, CarbonImmutable::parse('2026-08-03'), 7);

        $this->assertCount(1, $forecast->points);
        $this->assertSame(12.0, $forecast->points[0]->cloudCoverPercent);
        $this->assertSame(9.0, $forecast->points[0]->dewPointCelsius);
        Http::assertSent(fn (Request $request): bool => $request['start_date'] === '2026-08-03'
            && $request['end_date'] === '2026-08-09'
            && str_contains((string) $request->url(), '/v1/forecast'));
    }

    public function test_composite_provider_merges_seven_timer_astro_onto_open_meteo(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'api.open-meteo.com/*' => Http::response([
                'hourly' => [
                    'time' => ['2026-08-03T23:00', '2026-08-04T02:00', '2026-08-06T23:00'],
                    'cloud_cover' => [12, 20, 40],
                    'cloud_cover_low' => [4, 5, 10],
                    'cloud_cover_mid' => [7, 8, 12],
                    'cloud_cover_high' => [10, 11, 15],
                    'relative_humidity_2m' => [72, 70, 65],
                    'temperature_2m' => [14, 13, 12],
                    'dew_point_2m' => [9, 8, 7],
                    'wind_speed_10m' => [8, 7, 6],
                    'wind_gusts_10m' => [15, 14, 12],
                    'precipitation_probability' => [2, 5, 10],
                    'visibility' => [28000, 30000, 25000],
                ],
            ]),
            'www.7timer.info/*' => Http::response([
                'product' => 'astro',
                'init' => '2026080312',
                'dataseries' => [
                    ['timepoint' => 11, 'seeing' => 2, 'transparency' => 3],
                    ['timepoint' => 14, 'seeing' => 3, 'transparency' => 2],
                ],
            ]),
        ]);

        config([
            'nightscope.weather.driver' => 'open_meteo',
            'nightscope.weather.seven_timer.enabled' => true,
        ]);

        $location = ObservingLocation::factory()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'UTC',
        ]);

        $forecast = $this->app->make(WeatherProvider::class)
            ->forecast($location, CarbonImmutable::parse('2026-08-03', 'UTC'), 7);

        $this->assertSame('open-meteo+7timer', $forecast->provider);
        $this->assertCount(3, $forecast->points);
        $this->assertSame(2.0, $forecast->points[0]->seeingIndex);
        $this->assertSame(3.0, $forecast->points[0]->transparencyIndex);
        $this->assertSame(3.0, $forecast->points[1]->seeingIndex);
        $this->assertNull($forecast->points[2]->seeingIndex);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '7timer.info')
            && $request['product'] === 'astro');
    }

    public function test_forecast_page_displays_seven_local_nights(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $location = ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $this->app->instance(WeatherProvider::class, new class implements WeatherProvider
        {
            public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days): WeatherForecast
            {
                $points = [];

                for ($hour = 0; $hour < 24 * 8; $hour++) {
                    $points[] = new WeatherPoint($from->addHours($hour), 10, 5, 5, 5, 60, 12, 6, 5, 10, 0, 30000);
                }

                return new WeatherForecast('fake', $from, $points);
            }
        });

        $this->actingAs($user)
            ->get(route('admin.nightscope.forecast', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee($location->name)
            ->assertSee('Fenêtres météo astrophotographiques')
            ->assertSee('Voir les cibles');
    }

    public function test_tonight_weather_widget_displays_the_dew_point(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $this->app->instance(WeatherProvider::class, new class implements WeatherProvider
        {
            public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days): WeatherForecast
            {
                $points = [];

                for ($hour = 0; $hour < 24 * 8; $hour++) {
                    $points[] = new WeatherPoint($from->addHours($hour), 10, 5, 5, 5, 60, 12, 6.5, 5, 10, 0, 30000);
                }

                return new WeatherForecast('fake', $from, $points);
            }
        });

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('Point de rosée')
            ->assertSee('6,5 °C');
    }
}
