<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncAstrophotoDistancesTest extends TestCase
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

    public function test_admin_refreshes_main_object_and_field_distances_bypassing_the_cache(): void
    {
        $object = CelestialObject::factory()->create(['catalog' => 'NGC', 'catalog_number' => '7023', 'name' => null, 'distance_ly' => null]);
        Cache::put('simbad:distance-ly:ngc 7023', ['light_years' => null], now()->addDay());
        Http::fake(['simbad.example.test/tap*' => $this->simbadDistanceResponse('0.4', 'kpc')]);
        $photo = Astrophoto::factory()->create([
            'celestial_object_id' => $object->id,
            'annotations' => [['name' => 'NGC 7023', 'x' => 10, 'y' => 20, 'r' => 30]],
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.astrophotos.edit', $photo))
            ->post(route('admin.astrophotos.distances', $photo))
            ->assertRedirect(route('admin.astrophotos.edit', $photo))
            ->assertSessionHas('success', fn (string $message): bool => str_contains($message, 'NGC 7023 : 1') && str_contains($message, '1/1 objet(s) du champ'));

        $this->assertEqualsWithDelta(1304.6, $object->fresh()->distance_ly, 1);
        $this->assertNotNull($object->fresh()->distance_checked_at);
        $this->assertEqualsWithDelta(1304.6, $photo->fresh()->annotations[0]['distance_ly'], 1);
        $this->assertSame(10, $photo->fresh()->annotations[0]['x']);
    }

    public function test_known_distances_are_kept_when_simbad_is_unreachable(): void
    {
        Http::fake(['simbad.example.test/tap*' => Http::response('', 503)]);
        $object = CelestialObject::factory()->create(['distance_ly' => 1300.0]);
        $photo = Astrophoto::factory()->create([
            'celestial_object_id' => $object->id,
            'annotations' => [['name' => 'NGC 7023', 'x' => 1, 'y' => 1, 'r' => 1, 'distance_ly' => 1300.0]],
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.astrophotos.distances', $photo))
            ->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'SIMBAD ne répond pas'));

        $this->assertSame(1300.0, $object->fresh()->distance_ly);
        $this->assertEquals(1300, $photo->fresh()->annotations[0]['distance_ly']);
    }

    public function test_photo_without_object_nor_annotations_has_nothing_to_sync(): void
    {
        Http::fake();
        $photo = Astrophoto::factory()->create(['celestial_object_id' => null, 'annotations' => null]);

        $this->actingAs($this->admin())
            ->post(route('admin.astrophotos.distances', $photo))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_guest_cannot_sync_distances(): void
    {
        $photo = Astrophoto::factory()->create();

        $this->post(route('admin.astrophotos.distances', $photo))->assertRedirect(route('admin.login'));
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }
}
