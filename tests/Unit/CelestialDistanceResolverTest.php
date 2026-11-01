<?php

namespace Tests\Unit;

use App\Models\CelestialObject;
use App\Services\Astronomy\CelestialDistanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CelestialDistanceResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_simbad_tap_distances_to_light_years_median(): void
    {
        config(['nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap']);
        Http::fake([
            'simbad.example.test/tap*' => Http::response(
                "id,dist,unit\nM  31,0.78,Mpc\nM  31,779.0,kpc\nM  31,0.8,Mpc\n"
            ),
        ]);

        $distance = (new CelestialDistanceResolver)->lookupLightYears('M 31');

        $this->assertNotNull($distance);
        $this->assertEqualsWithDelta(2_543_017, $distance, 1_000);
    }

    public function test_it_persists_missing_distance_on_celestial_object(): void
    {
        config(['nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap']);
        Http::fake([
            'simbad.example.test/tap*' => $this->simbadDistanceResponse(),
        ]);

        $object = CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => '31',
            'distance_ly' => null,
        ]);

        $distance = (new CelestialDistanceResolver)->fillMissing($object);

        $this->assertNotNull($distance);
        $this->assertEqualsWithDelta($distance, $object->fresh()->distance_ly, 0.1);
    }

    public function test_it_returns_null_when_simbad_has_no_distance(): void
    {
        config(['nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap']);
        Http::fake([
            'simbad.example.test/tap*' => $this->simbadDistanceResponse(null),
        ]);

        $this->assertNull((new CelestialDistanceResolver)->lookupLightYears('Unknown Object'));
    }

    public function test_one_request_resolves_several_identifiers_in_simbad_spelling(): void
    {
        config(['nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap']);
        Http::fake([
            'simbad.example.test/tap*' => fn ($request) => str_contains($request->data()['QUERY'], 'mesDistance')
                ? Http::response("id,dist,unit\nNGC   281,2.9,kpc\nNAME Deneb,0.8,kpc\nNAME Deneb,1.0,kpc\n* alf Cyg,0.9,kpc\n")
                : Http::response("id,plx_value,plx_err\n"),
        ]);

        $distances = (new CelestialDistanceResolver)->lookupMany(['NGC 281', 'Deneb', 'IC 5070']);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => str_contains($request->data()['QUERY'], 'FROM basic') && str_contains($request->data()['QUERY'], "'IC 5070'") && ! str_contains($request->data()['QUERY'], 'Deneb'));
        Http::assertSent(fn ($request): bool => str_contains($request->data()['QUERY'], "'NGC  281'") && str_contains($request->data()['QUERY'], "'NAME Deneb'"));
        $this->assertEqualsWithDelta(9458.5, $distances['NGC 281'], 1);
        $this->assertEqualsWithDelta(2609.2, $distances['Deneb'], 1);
        $this->assertNull($distances['IC 5070']);

        (new CelestialDistanceResolver)->lookupMany(['NGC 281', 'IC 5070']);
        Http::assertSentCount(2);
    }

    public function test_parallax_is_used_when_no_distance_was_published_and_imprecise_parallaxes_are_ignored(): void
    {
        config(['nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap']);
        Http::fake([
            'simbad.example.test/tap*' => fn ($request) => str_contains($request->data()['QUERY'], 'mesDistance')
                ? Http::response("id,dist,unit\n")
                : Http::response("id,plx_value,plx_err\nNGC  7023,2.905,0.077\nNGC  7380,0.333,0.2\n"),
        ]);

        $distances = (new CelestialDistanceResolver)->lookupMany(['NGC 7023', 'NGC 7380']);

        $this->assertEqualsWithDelta(1122.8, $distances['NGC 7023'], 1);
        $this->assertNull($distances['NGC 7380']);
    }

    public function test_harvard_mirror_answers_when_strasbourg_is_down(): void
    {
        config([
            'nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap',
            'nightscope.distances.simbad_tap_mirrors' => ['https://mirror.example.test/tap'],
        ]);
        Http::fake([
            'simbad.example.test/tap*' => Http::response('', 503),
            'mirror.example.test/tap*' => $this->simbadDistanceResponse('0.78', 'Mpc'),
        ]);

        $resolver = new CelestialDistanceResolver;

        $this->assertEqualsWithDelta(2_544_017, $resolver->lookupLightYears('M 31'), 10);
        $this->assertFalse($resolver->wasUnreachable());

        $resolver->lookupMany(['M 33'], refresh: true);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'simbad.example.test') && str_contains($request->data()['QUERY'] ?? '', 'M  33'));
    }

    public function test_unreachable_simbad_is_reported_and_not_cached(): void
    {
        config(['nightscope.distances.simbad_tap_url' => 'https://simbad.example.test/tap']);
        Http::fake(['simbad.example.test/tap*' => Http::response('', 503)]);

        $resolver = new CelestialDistanceResolver;

        $this->assertNull($resolver->lookupLightYears('M 31'));
        $this->assertTrue($resolver->wasUnreachable());
    }

    public function test_simbad_variants_cover_padded_catalogue_numbers_names_and_stars(): void
    {
        $this->assertContains('M  31', CelestialDistanceResolver::simbadVariants('M 031'));
        $this->assertContains('NGC  224', CelestialDistanceResolver::simbadVariants('NGC224'));
        $this->assertContains('IC 1805', CelestialDistanceResolver::simbadVariants('IC 1805'));
        $this->assertContains('NAME Deneb', CelestialDistanceResolver::simbadVariants('Deneb'));
        $this->assertContains('* alf Cyg', CelestialDistanceResolver::simbadVariants('α Cyg'));
        $this->assertContains('* 50 Cyg', CelestialDistanceResolver::simbadVariants('50 Cyg'));
    }
}
