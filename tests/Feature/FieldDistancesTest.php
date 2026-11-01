<?php

namespace Tests\Feature;

use App\Enums\AstrometryStatus;
use App\Models\Astrophoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FieldDistancesTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_objects_get_their_distance_in_one_batched_simbad_lookup(): void
    {
        config(['nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap']);
        // Une requête groupée pour les distances publiées, une pour la parallaxe des objets restants.
        Http::fake(['simbad.example.test/tap*' => Http::response("id,dist,unit\nNGC 7000,0.8,kpc\nNAME Deneb,0.8,kpc\n")]);
        $photo = Astrophoto::factory()->create([
            'astrometry_status' => AstrometryStatus::Solved,
            'annotations' => [
                ['name' => 'NGC 7000 / North America Nebula', 'x' => 10, 'y' => 20, 'r' => 30],
                ['name' => 'Deneb', 'x' => 40, 'y' => 50, 'r' => 0],
                ['name' => 'IC 5067', 'x' => 60, 'y' => 70, 'r' => 5],
            ],
        ]);
        Astrophoto::factory()->create(['astrometry_status' => AstrometryStatus::Solving, 'annotations' => [['name' => 'M 31', 'x' => 1, 'y' => 1, 'r' => 1]]]);

        $this->artisan('astrophotos:enrich-field-distances')
            ->expectsOutputToContain('1 photo(s) traitée(s), 2 distance(s)')
            ->assertSuccessful();

        Http::assertSentCount(2);
        $annotations = $photo->fresh()->annotations;
        $this->assertEqualsWithDelta(2609.2, $annotations[0]['distance_ly'], 1);
        $this->assertEqualsWithDelta(2609.2, $annotations[1]['distance_ly'], 1);
        $this->assertNull($annotations[2]['distance_ly']);
        $this->assertSame(10, $annotations[0]['x']);

        $this->artisan('astrophotos:enrich-field-distances')->expectsOutputToContain('0 photo(s) traitée(s)');
        Http::assertSentCount(2);
    }
}
