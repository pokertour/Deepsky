<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Models\SetupItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPhotoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_page_shows_story_catalog_and_equipment_without_private_location(): void
    {
        $object = CelestialObject::factory()->create(['catalog' => 'M', 'catalog_number' => '31', 'constellation' => 'And', 'distance_ly' => 2540000]);
        $rig = EquipmentSetup::factory()->create(['name' => 'Rig public']);
        $location = ObservingLocation::factory()->create(['name' => 'Adresse privée', 'region' => 'Région publique']);
        $photo = Astrophoto::factory()->create(['title' => 'Photo dédiée', 'description' => "Une nuit sous les étoiles.\nTraitement soigné.", 'celestial_object_id' => $object->id, 'equipment_setup_id' => $rig->id, 'observing_location_id' => $location->id]);

        $this->withoutVite()->get(route('astrophoto.show', $photo))->assertOk()
            ->assertSee('Une nuit sous les étoiles.')->assertSee('Traitement soigné.')
            ->assertSee('Rig public')->assertSee('M 31')->assertSee('2,54 M al')
            ->assertSee('Région publique')->assertDontSee('Adresse privée');
    }

    public function test_drafts_are_hidden_from_all_public_photo_endpoints_but_admin_can_preview(): void
    {
        $photo = Astrophoto::factory()->create(['is_published' => false, 'title' => 'Brouillon secret']);
        $this->withoutVite()->get(route('welcome'))->assertOk()->assertDontSee('Brouillon secret');
        $this->get(route('astrophoto.show', $photo))->assertNotFound();
        $this->get(route('astrophoto.full', $photo))->assertNotFound();
        $this->post(route('astrophoto.click', $photo))->assertNotFound();
        $this->post(route('astrophoto.like', $photo))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('astrophoto.show', $photo))->assertNotFound();
        config(['admin.emails' => ['owner@example.com']]);
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->get(route('astrophoto.show', $photo))->assertOk()->assertSee('Aperçu privé')
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('noindex, nofollow');
        $this->get(route('astrophoto.full', $photo))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_filters_combine_cluster_constellation_year_and_rig(): void
    {
        $rig = EquipmentSetup::factory()->create();
        $object = CelestialObject::factory()->create(['type' => 'cluster', 'constellation' => 'Tau']);
        Astrophoto::factory()->create(['title' => 'Amas filtré', 'celestial_object_id' => $object->id, 'equipment_setup_id' => $rig->id, 'captured_at' => '2025-12-01', 'tags' => []]);
        Astrophoto::factory()->create(['title' => 'Autre année', 'celestial_object_id' => $object->id, 'equipment_setup_id' => $rig->id, 'captured_at' => '2024-12-01']);
        Astrophoto::factory()->create(['title' => 'Autre rig', 'celestial_object_id' => $object->id, 'captured_at' => '2025-12-01']);

        $this->withoutVite()->get(route('welcome', ['type' => 'cluster', 'constellation' => 'Tau', 'year' => 2025, 'equipment' => 'rig:'.$rig->id]))
            ->assertOk()->assertSee('Amas filtré')->assertDontSee('Autre année')->assertDontSee('Autre rig')
            ->assertSee('Tout réinitialiser');
    }

    public function test_equipment_filter_matches_legacy_photo_and_rig_item(): void
    {
        $camera = SetupItem::factory()->camera()->create();
        $rig = EquipmentSetup::factory()->create();
        $rig->setupItems()->attach($camera, ['enabled' => true]);
        Astrophoto::factory()->create(['title' => 'Caméra historique', 'camera_id' => $camera->id]);
        Astrophoto::factory()->create(['title' => 'Caméra via rig', 'equipment_setup_id' => $rig->id]);
        Astrophoto::factory()->create(['title' => 'Autre caméra']);

        $this->withoutVite()->get(route('welcome', ['equipment' => 'item:'.$camera->id]))
            ->assertOk()->assertSee('Caméra historique')->assertSee('Caméra via rig')->assertDontSee('Autre caméra');
    }

    public function test_drafts_do_not_leak_into_versions_filter_options_or_observatory_totals(): void
    {
        $rig = EquipmentSetup::factory()->create(['name' => 'Rig secret']);
        $object = CelestialObject::factory()->create(['constellation' => 'Constellation secrète']);
        $draft = Astrophoto::factory()->create(['target' => 'M31', 'is_published' => false, 'equipment_setup_id' => $rig->id, 'celestial_object_id' => $object->id, 'exposure' => 360000]);
        $public = Astrophoto::factory()->create(['target' => 'M31', 'exposure' => 3600]);

        $this->withoutVite()->get(route('welcome'))->assertOk()->assertDontSee('Rig secret')
            ->assertDontSee('Constellation secrète')->assertSee('&quot;versions&quot;:[]', false);
        $this->get(route('astrophoto.show', $public))->assertOk()
            ->assertViewHas('versions', fn ($versions) => $versions->isEmpty())
            ->assertViewHas('next', fn ($next) => $next === null);
        $this->get(route('observatory'))->assertOk()->assertViewHas('totals', fn ($totals) => $totals['photos'] === 1 && $totals['hours'] === 1.0);
        $this->assertSame(0, $draft->fresh()->views_count);
    }
}
