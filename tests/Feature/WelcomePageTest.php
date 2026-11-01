<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_lists_photos(): void
    {
        Astrophoto::factory()->create(['title' => 'Andromeda Galaxy']);

        $this->withoutVite()
            ->get(route('welcome'))
            ->assertOk()
            ->assertSee('Andromeda Galaxy');
    }

    public function test_search_filters_photos_by_title(): void
    {
        Astrophoto::factory()->create(['title' => 'Orion Nebula']);
        Astrophoto::factory()->create(['title' => 'Whirlpool Galaxy']);

        $this->withoutVite()
            ->get(route('welcome', ['search' => 'Orion']))
            ->assertOk()
            ->assertSee('Orion Nebula')
            ->assertDontSee('Whirlpool Galaxy');
    }

    public function test_type_filter_matches_tags(): void
    {
        Astrophoto::factory()->create(['title' => 'Galaxy Shot', 'tags' => ['galaxy']]);
        Astrophoto::factory()->create(['title' => 'Nebula Shot', 'tags' => ['nebula']]);

        $this->withoutVite()
            ->get(route('welcome', ['type' => 'galaxy']))
            ->assertOk()
            ->assertSee('Galaxy Shot')
            ->assertDontSee('Nebula Shot');
    }

    public function test_homepage_shows_linked_celestial_catalog_data(): void
    {
        $object = CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => '31',
            'name' => 'Andromeda',
            'type' => 'galaxy',
            'constellation' => 'And',
            'magnitude' => 3.4,
            'distance_ly' => 2_540_000,
            'angular_width_arcmin' => 190.0,
            'angular_height_arcmin' => 60.0,
        ]);

        Astrophoto::factory()->create([
            'title' => 'Andromeda publique',
            'celestial_object_id' => $object->id,
        ]);

        $this->withoutVite()
            ->get(route('welcome'))
            ->assertOk()
            ->assertSee('Andromeda publique')
            ->assertSee('M 31')
            ->assertSee('And')
            ->assertSee('mag 3,4')
            ->assertSee('2,54 M al')
            ->assertSee('&quot;designation&quot;:&quot;M 31&quot;', false)
            ->assertSee('&quot;distance&quot;:&quot;2,54 M al&quot;', false)
            ->assertSee('OBJET CÉLESTE');
    }

    public function test_search_finds_photos_by_celestial_designation(): void
    {
        $object = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => '7000',
            'name' => 'North America Nebula',
            'constellation' => 'Cyg',
        ]);

        Astrophoto::factory()->create([
            'title' => 'Photo Amérique du Nord',
            'celestial_object_id' => $object->id,
        ]);
        Astrophoto::factory()->create(['title' => 'Autre photo']);

        $this->withoutVite()
            ->get(route('welcome', ['search' => 'NGC 7000']))
            ->assertOk()
            ->assertSee('Photo Amérique du Nord')
            ->assertDontSee('Autre photo');
    }

    public function test_full_page_includes_celestial_distance_in_meta_description(): void
    {
        $object = CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => '42',
            'name' => 'Orion Nebula',
            'constellation' => 'Ori',
            'distance_ly' => 1344,
        ]);

        $photo = Astrophoto::factory()->create([
            'title' => 'Orion full',
            'celestial_object_id' => $object->id,
        ]);

        $this->withoutVite()
            ->get(route('astrophoto.full', $photo))
            ->assertOk()
            ->assertSee('M 42 — Orion Nebula')
            ->assertSee('1 344 al')
            ->assertSee('Ori');
    }

    public function test_visit_is_counted_once_and_flagged_in_session(): void
    {
        $this->withoutVite()
            ->get(route('welcome'))
            ->assertOk()
            ->assertSessionHas('visit_counted', true);

        $this->assertSame(1, (int) DB::table('site_settings')->where('key', 'total_visits')->value('value'));
    }

    public function test_bots_are_not_counted_as_visits(): void
    {
        $this->withoutVite()
            ->withHeader('User-Agent', 'Mozilla/5.0 (compatible; bingbot/2.0)')
            ->get(route('welcome'))
            ->assertOk();

        $this->assertSame(0, (int) DB::table('site_settings')->where('key', 'total_visits')->value('value'));
    }

    public function test_visitor_without_session_cookie_is_counted_once(): void
    {
        $this->withoutVite()->get(route('welcome'))->assertOk();
        $this->flushSession();
        $this->withoutVite()->get(route('welcome'))->assertOk();

        $this->assertSame(1, (int) DB::table('site_settings')->where('key', 'total_visits')->value('value'));
    }

    public function test_visit_is_not_counted_again_for_a_flagged_session(): void
    {
        $this->withoutVite()
            ->withSession(['visit_counted' => true])
            ->get(route('welcome'))
            ->assertOk();

        $this->assertSame(0, (int) DB::table('site_settings')->where('key', 'total_visits')->value('value'));
    }
}
