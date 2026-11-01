<?php

namespace Tests\Feature;

use App\Jobs\EnrichCelestialDistanceJob;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\ObservingLocation;
use App\Models\User;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EnrichCelestialDistancesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'admin.emails' => ['owner@example.com'],
            'nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap',
        ]);
    }

    public function test_command_fills_missing_distances(): void
    {
        Http::fake([
            'simbad.example.test/tap*' => $this->simbadDistanceResponse(),
        ]);

        $object = CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => '31',
            'type' => 'galaxy',
            'magnitude' => 3.4,
            'distance_ly' => null,
        ]);

        $this->artisan('nightscope:enrich-distances --limit=5')
            ->assertSuccessful();

        $this->assertNotNull($object->fresh()->distance_ly);
    }

    public function test_target_detail_can_refresh_distance_from_simbad(): void
    {
        Http::fake([
            'simbad.example.test/tap*' => $this->simbadDistanceResponse(),
        ]);

        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $object = CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => '31',
            'ra_deg' => 10.6847,
            'dec_deg' => 41.269,
            'distance_ly' => null,
        ]);

        $this->actingAs($user)
            ->from(route('admin.nightscope.targets.show', $object))
            ->post(route('admin.nightscope.targets.distance', $object))
            ->assertRedirect();

        $this->assertNotNull($object->fresh()->distance_ly);
    }

    public function test_unresolved_objects_are_skipped_until_recheck_delay(): void
    {
        $simbadKnowsObject = false;
        Http::fake(['simbad.example.test/tap*' => function (Request $request) use (&$simbadKnowsObject) {
            return $this->simbadDistanceResponse($simbadKnowsObject ? '0.78' : null)($request);
        }]);
        $unknown = CelestialObject::factory()->create(['type' => 'galaxy', 'magnitude' => 1.0, 'distance_ly' => null]);

        $this->artisan('nightscope:enrich-distances --limit=5')->assertSuccessful();
        $this->assertNotNull($unknown->fresh()->distance_checked_at);
        $this->assertNull($unknown->fresh()->distance_ly);

        $simbadKnowsObject = true;
        $next = CelestialObject::factory()->create(['type' => 'galaxy', 'magnitude' => 9.0, 'distance_ly' => null]);

        $this->artisan('nightscope:enrich-distances --limit=1')->assertSuccessful();
        $this->assertNotNull($next->fresh()->distance_ly);

        $this->travel(31)->days();
        $this->artisan('nightscope:enrich-distances --limit=1')->assertSuccessful();
        $this->assertNotNull($unknown->fresh()->distance_ly);
    }

    public function test_photographed_objects_are_enriched_first(): void
    {
        Http::fake(['simbad.example.test/tap*' => $this->simbadDistanceResponse()]);
        Queue::fake();
        $bright = CelestialObject::factory()->create(['type' => 'galaxy', 'magnitude' => 1.0, 'distance_ly' => null]);
        $photographed = CelestialObject::factory()->create(['type' => 'galaxy', 'magnitude' => 12.0, 'distance_ly' => null]);
        Astrophoto::factory()->create(['celestial_object_id' => $photographed->id]);

        $this->artisan('nightscope:enrich-distances --limit=1')->assertSuccessful();

        $this->assertNotNull($photographed->fresh()->distance_ly);
        $this->assertNull($bright->fresh()->distance_ly);
    }

    public function test_linking_a_photo_dispatches_distance_enrichment(): void
    {
        Queue::fake();
        $object = CelestialObject::factory()->create(['distance_ly' => null]);
        $known = CelestialObject::factory()->create(['distance_ly' => 1344.0]);
        $recentlyChecked = CelestialObject::factory()->create(['distance_ly' => null, 'distance_checked_at' => now()->subDay()]);

        Astrophoto::factory()->create(['celestial_object_id' => $object->id]);
        Astrophoto::factory()->create(['celestial_object_id' => $known->id]);
        Astrophoto::factory()->create(['celestial_object_id' => $recentlyChecked->id]);

        Queue::assertPushed(EnrichCelestialDistanceJob::class, 1);
        Queue::assertPushed(EnrichCelestialDistanceJob::class, fn (EnrichCelestialDistanceJob $job): bool => $job->celestialObject->is($object));
    }

    public function test_enrichment_job_fills_distance(): void
    {
        Http::fake(['simbad.example.test/tap*' => $this->simbadDistanceResponse()]);
        $object = CelestialObject::factory()->create(['catalog' => 'M', 'catalog_number' => '31', 'distance_ly' => null]);

        EnrichCelestialDistanceJob::dispatchSync($object);

        $this->assertEqualsWithDelta(2_544_016.8, $object->fresh()->distance_ly, 1);
        $this->assertNotNull($object->fresh()->distance_checked_at);
    }

    public function test_photo_page_shows_physical_size(): void
    {
        $object = CelestialObject::factory()->create([
            'distance_ly' => 2_540_000.0,
            'angular_width_arcmin' => 190.0,
            'angular_height_arcmin' => 60.0,
        ]);
        $photo = Astrophoto::factory()->create(['celestial_object_id' => $object->id]);

        $this->withoutVite()->get(route('astrophoto.show', $photo))
            ->assertOk()
            ->assertSee('Taille réelle')
            ->assertSee('≈ 140,4 k al');
    }

    public function test_distance_enrichment_is_scheduled_daily(): void
    {
        $this->app->make(Kernel::class)->bootstrap();

        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains((string) $event->command, 'nightscope:enrich-distances'));

        $this->assertNotNull($event);
        $this->assertSame('40 3 * * *', $event->expression);
    }
}
