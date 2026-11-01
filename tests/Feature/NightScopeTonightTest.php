<?php

namespace Tests\Feature;

use App\Data\Equipment\SetupItemSpecs;
use App\Enums\SetupItemType;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Models\SetupItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NightScopeTonightTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    public function test_tonight_page_scores_visible_targets(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $target = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'TEST',
            'name' => 'Cible circumpolaire',
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 5,
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('Que photographier ce soir ?')
            ->assertSee($target->designation())
            ->assertSee('Cible circumpolaire')
            ->assertSee('Galaxy')
            ->assertSee('admin/nightscope/preview', false)
            ->assertSee('Direction')
            ->assertSee('x-show="nightScopeOpen"', false)
            ->assertSee('Catalogue céleste')
            ->assertSee('aria-label="NightScope"', false)
            ->assertSee('>Prévisions</a>', false)
            ->assertSee('Types d’objets')
            ->assertSee('Galaxies')
            ->assertSee('Nébuleuses')
            ->assertSee('Amas')
            ->assertSee('Jamais photographié')
            ->assertSee('Lever')
            ->assertSee('Coucher');
    }

    public function test_tonight_can_filter_never_photographed_targets(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $photographed = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'PHOTO',
            'name' => 'Deja photographiee',
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 4,
        ]);
        $fresh = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'FRESH',
            'name' => 'Jamais vue',
            'type' => 'galaxy',
            'ra_deg' => 40.0,
            'dec_deg' => 88.5,
            'magnitude' => 9,
        ]);
        Astrophoto::factory()->create([
            'celestial_object_id' => $photographed->id,
            'captured_at' => '2026-07-01',
            'exposure' => 3600,
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', [
                'date' => '2026-08-03',
                'types' => ['galaxy'],
                'never_photographed' => 1,
            ]))
            ->assertOk()
            ->assertSee('Jamais vue')
            ->assertSee($fresh->designation())
            ->assertDontSee('Deja photographiee')
            ->assertDontSee($photographed->designation());
    }

    public function test_tonight_can_filter_recommendations_by_object_type(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $galaxy = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'GAL',
            'name' => 'Galaxie filtre',
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 5,
        ]);
        $nebula = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'NEB',
            'name' => 'Nebuleuse filtre',
            'type' => 'nebula',
            'ra_deg' => 40.0,
            'dec_deg' => 88.0,
            'magnitude' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', [
                'date' => '2026-08-03',
                'types' => ['galaxy'],
            ]))
            ->assertOk()
            ->assertSee('Galaxie filtre')
            ->assertDontSee('Nebuleuse filtre')
            ->assertSee($galaxy->designation())
            ->assertDontSee($nebula->designation());
    }

    public function test_tonight_can_load_more_recommendations(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);

        foreach (range(1, 15) as $index) {
            CelestialObject::factory()->create([
                'catalog' => 'NGC',
                'catalog_number' => (string) (1000 + $index),
                'name' => "Cible load {$index}",
                'type' => 'galaxy',
                'ra_deg' => 37.9546,
                'dec_deg' => 89.2641 - ($index * 0.01),
                'magnitude' => 4 + ($index * 0.1),
            ]);
        }

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', [
                'date' => '2026-08-03',
                'types' => ['galaxy'],
                'search' => 'Cible load',
            ]))
            ->assertOk()
            ->assertSee('Charger plus de recommandations')
            ->assertSee('search=Cible%20load', false);

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', [
                'date' => '2026-08-03',
                'types' => ['galaxy'],
                'limit' => 24,
                'search' => 'Cible load',
            ]))
            ->assertOk()
            ->assertSee('Cible load 1')
            ->assertSee('15 recommandations');
    }

    public function test_tonight_searches_before_the_candidate_limit_and_keeps_separate_cached_results(): void
    {
        config(['nightscope.tonight.candidate_limit' => 1]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        CelestialObject::factory()->create([
            'name' => 'Autre galaxie brillante',
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 1,
        ]);
        $target = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => '7000',
            'name' => 'Cible recherchee',
            'constellation' => 'Cepheus',
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 9,
        ]);

        $this->actingAs($user)->get(route('admin.nightscope.tonight', ['date' => '2026-08-03']))
            ->assertOk()->assertSee('Autre galaxie brillante')->assertDontSee($target->name);

        foreach (['recherchee', 'NGC7000', 'NGC 7000', 'Cepheus'] as $search) {
            $this->get(route('admin.nightscope.tonight', ['date' => '2026-08-03', 'search' => $search]))
                ->assertOk()
                ->assertSee($target->name)
                ->assertDontSee('Autre galaxie brillante')
                ->assertViewHas('data', fn ($data) => $data->search === $search);
        }

        $this->get(route('admin.nightscope.tonight', ['date' => '2026-08-03', 'search' => 'Introuvable']))
            ->assertOk()->assertSee('Aucune cible ne correspond à cette recherche');
    }

    public function test_tonight_search_respects_type_and_photographed_filters(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        foreach (['galaxy', 'nebula'] as $type) {
            CelestialObject::factory()->create([
                'name' => 'Recherche '.$type,
                'type' => $type,
                'ra_deg' => 37.9546,
                'dec_deg' => 89.2641,
            ]);
        }
        $photographed = CelestialObject::factory()->create([
            'name' => 'Recherche photographiee',
            'type' => 'galaxy',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
        ]);
        Astrophoto::factory()->create(['celestial_object_id' => $photographed->id]);

        $this->actingAs($user)->get(route('admin.nightscope.tonight', [
            'date' => '2026-08-03',
            'search' => 'Recherche',
            'types' => ['galaxy'],
            'never_photographed' => 1,
        ]))->assertOk()
            ->assertSee('Recherche galaxy')
            ->assertDontSee('Recherche nebula')
            ->assertDontSee('Recherche photographiee');
    }

    public function test_target_detail_renders_altitude_graph(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $target = CelestialObject::factory()->create([
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
        ]);
        $rig = EquipmentSetup::factory()->default()->create(['name' => 'Rig de cadrage']);
        $tube = SetupItem::factory()->create([
            'type' => SetupItemType::OpticalTube,
            'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
        ]);
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['sensor_width_mm' => 11.31, 'sensor_height_mm' => 11.31, 'pixel_size_um' => 3.76]),
        ]);
        $rig->setupItems()->attach([$tube->id => ['enabled' => true], $camera->id => ['enabled' => true]]);
        Astrophoto::factory()->create([
            'celestial_object_id' => $target->id,
            'equipment_setup_id' => $rig->id,
            'captured_at' => '2026-07-01',
            'exposure' => 7200,
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.targets.show', [
                'celestialObject' => $target,
                'date' => '2026-08-03',
            ]))
            ->assertOk()
            ->assertSee('Courbe d’altitude')
            ->assertSee('Informations cible')
            ->assertSee('Catalogue')
            ->assertSee($target->designation())
            ->assertSee('Pointage')
            ->assertSee('Champ réellement couvert par ton Rig')
            ->assertSee('Rig de cadrage')
            ->assertSee('2 h 0')
            ->assertSee('<svg', false);
    }

    public function test_tonight_page_prompts_for_location_when_none_exists(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight'))
            ->assertOk()
            ->assertSee('Lieu d’observation requis');
    }

    public function test_candidates_are_the_objects_actually_up_tonight_not_the_brightest_of_the_year(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        config(['nightscope.tonight.candidate_limit' => 20]);
        CelestialObject::factory()->count(30)->sequence(fn ($sequence): array => [
            'catalog' => 'M',
            'catalog_number' => 'SPRING-'.$sequence->index,
            'type' => 'galaxy',
            'ra_deg' => 195 + $sequence->index,
            'dec_deg' => -10,
            'magnitude' => 2,
        ])->create();
        $autumnTarget = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'AUTUMN',
            'type' => 'galaxy',
            'ra_deg' => 0,
            'dec_deg' => 40,
            'magnitude' => 9,
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', ['date' => '2026-10-30']))
            ->assertOk()
            ->assertSee($autumnTarget->designation())
            ->assertDontSee('SPRING-0');
    }

    public function test_short_summer_nights_fall_back_to_the_nautical_window(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 51.5,
            'longitude' => 2.3,
            'timezone' => 'Europe/Paris',
        ]);
        $target = CelestialObject::factory()->create([
            'catalog_number' => 'SUMMER',
            'type' => 'nebula',
            'ra_deg' => 314.75,
            'dec_deg' => 44.33,
            'magnitude' => 4,
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.tonight', ['date' => '2026-06-21']))
            ->assertOk()
            ->assertSee('cibles calculées sur la nuit nautique')
            ->assertSee($target->designation());
    }
}
