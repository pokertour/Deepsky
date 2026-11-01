<?php

namespace Tests\Feature;

use App\Jobs\RunScheduledCommandJob;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResyncAstrophotoDistancesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'admin.emails' => ['owner@example.com'],
            'nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap',
        ]);
    }

    public function test_every_photo_is_resynced_bypassing_the_cache(): void
    {
        Http::fake(['simbad.example.test/tap*' => $this->simbadDistanceResponse('0.4', 'kpc')]);
        $object = CelestialObject::factory()->create(['catalog' => 'NGC', 'catalog_number' => '7023', 'name' => null, 'distance_ly' => 900.0]);
        Cache::put('simbad:distance-ly:ngc 7023', ['light_years' => 900.0], now()->addDays(30));
        $photo = Astrophoto::factory()->create([
            'celestial_object_id' => $object->id,
            'annotations' => [['name' => 'NGC 7023', 'x' => 1, 'y' => 1, 'r' => 1]],
        ]);
        CelestialObject::factory()->create(['distance_ly' => null]);

        $this->artisan('astrophotos:resync-distances')
            ->expectsOutputToContain('Objets principaux : 1/1 avec distance. Champ : 1/1 objet(s) avec distance sur 1 photo(s).')
            ->assertSuccessful();

        $this->assertEqualsWithDelta(1304.6, $object->fresh()->distance_ly, 1);
        $this->assertEqualsWithDelta(1304.6, $photo->fresh()->annotations[0]['distance_ly'], 1);
    }

    public function test_resync_stops_and_keeps_known_distances_when_simbad_is_unreachable(): void
    {
        Http::fake(['simbad.example.test/tap*' => Http::response('', 503)]);
        $object = CelestialObject::factory()->create(['distance_ly' => 1300.0]);
        Astrophoto::factory()->create(['celestial_object_id' => $object->id]);

        $this->artisan('astrophotos:resync-distances')
            ->expectsOutputToContain('SIMBAD ne répond pas')
            ->assertFailed();

        $this->assertSame(1300.0, $object->fresh()->distance_ly);
    }

    public function test_admin_launches_the_resync_from_the_photo_list(): void
    {
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->get(route('admin.astrophotos.index'))
            ->assertSee('astrophotos:resync-distances', false);

        $this->from(route('admin.astrophotos.index'))
            ->post(route('admin.tasks.store'), ['command' => 'astrophotos:resync-distances'])
            ->assertRedirect(route('admin.astrophotos.index'))
            ->assertSessionHas('success', '« Resynchronisation de toutes les distances » a été ajoutée à la file. Elle apparaîtra dans l’historique dès son démarrage.');

        Queue::assertPushed(RunScheduledCommandJob::class);
    }
}
