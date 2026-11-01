<?php

namespace Tests\Feature;

use App\Enums\AstrometryStatus;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Support\SkyDive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkyDiveTest extends TestCase
{
    use RefreshDatabase;

    private function solvedPhoto(): Astrophoto
    {
        return Astrophoto::factory()->create([
            'celestial_object_id' => CelestialObject::factory()->create(['catalog' => 'M', 'catalog_number' => '31', 'distance_ly' => 2_537_000])->id,
            'astrometry_status' => AstrometryStatus::Solved,
            'ra' => 10.685,
            'dec' => 41.269,
            'pixel_scale' => 9.0,
            'orientation' => 30,
            'annotations' => [
                ['name' => 'M 110', 'x' => 420, 'y' => 300, 'r' => 40, 'distance_ly' => 2_690_000],
                ['name' => 'NGC 206', 'x' => 520, 'y' => 470, 'r' => 15],
            ],
        ]);
    }

    public function test_solved_photos_offer_the_dive_with_light_ages_of_the_field(): void
    {
        $photo = $this->solvedPhoto();
        $dive = SkyDive::for($photo);

        $this->assertSame('M 31', $dive['focus']['label']);
        $this->assertSame(30.0, $dive['focus']['orientation']);
        $this->assertSame('au temps des premiers outils de pierre', $dive['focus']['light']['epoch']);
        $this->assertSame('2,7 millions d’années', $dive['focus']['annotations'][0]['light']['duration']);
        $this->assertNull($dive['focus']['annotations'][1]['light']);

        $this->get(route('astrophoto.show', $photo))->assertOk()->assertSee('Plonger vers l’objet');
    }

    public function test_unsolved_photos_have_no_dive(): void
    {
        $photo = Astrophoto::factory()->create(['astrometry_status' => AstrometryStatus::Idle]);

        $this->assertNull(SkyDive::for($photo));
        $this->get(route('astrophoto.show', $photo))->assertOk()->assertDontSee('Plonger vers l’objet');
    }
}
