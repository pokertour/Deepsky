<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_photos_are_ordered_by_light_age_between_earth_milestones(): void
    {
        $andromeda = CelestialObject::factory()->create(['catalog' => 'M', 'catalog_number' => '31', 'distance_ly' => 2_537_000]);
        $orion = CelestialObject::factory()->create(['catalog' => 'M', 'catalog_number' => '42', 'distance_ly' => 1_344.705]);
        Astrophoto::factory()->create(['title' => 'Andromède profonde', 'celestial_object_id' => $andromeda->id]);
        Astrophoto::factory()->create(['title' => 'Orion en Ha', 'celestial_object_id' => $orion->id]);
        Astrophoto::factory()->create(['title' => 'Brouillon caché', 'celestial_object_id' => $andromeda->id, 'is_published' => false]);
        Astrophoto::factory()->create(['title' => 'Sans distance', 'celestial_object_id' => CelestialObject::factory()->create(['distance_ly' => null])->id]);

        $this->get(route('time-machine'))
            ->assertOk()
            ->assertSee('Regarder loin, c’est regarder tôt')
            ->assertSeeInOrder(['Orion en Ha', 'Construction des pyramides de Gizeh', 'Apparition d’Homo sapiens', 'Andromède profonde'])
            ->assertSee('au temps des premiers outils de pierre')
            ->assertSee("1\u{202F}345 années-lumière")
            ->assertDontSee('344,705')
            ->assertDontSee('Brouillon caché')
            ->assertDontSee('Sans distance')
            ->assertDontSee('Disparition des dinosaures');
    }

    public function test_page_waits_for_distances_when_none_are_known(): void
    {
        $this->get(route('time-machine'))->assertOk()->assertSee('en cours de récupération');
    }
}
