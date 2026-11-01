<?php

namespace Tests\Feature;

use App\Data\Equipment\SetupItemSpecs;
use App\Enums\SetupItemType;
use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ImagingPlan;
use App\Models\ObservingLocation;
use App\Models\SetupItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NightScopeTargetDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    public function test_target_detail_shows_moon_curve_window_and_captures(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'name' => 'Maison',
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $target = CelestialObject::factory()->create([
            'name' => 'Cible test',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
        ]);
        $rig = EquipmentSetup::factory()->default()->create(['name' => 'Rig Principal']);
        $tube = SetupItem::factory()->create([
            'type' => SetupItemType::OpticalTube,
            'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
        ]);
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['sensor_width_mm' => 11.31, 'sensor_height_mm' => 11.31, 'pixel_size_um' => 3.76]),
        ]);
        $rig->setupItems()->attach([$tube->id => ['enabled' => true], $camera->id => ['enabled' => true]]);
        $photo = Astrophoto::factory()->create([
            'title' => 'Capture historique M31',
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
            ->assertSee('Trajectoire sur l’horizon')
            ->assertSee('data-samples=', false)
            ->assertSee('Parcourir la nuit')
            ->assertSee('Fenêtre ≥ 30°')
            ->assertSee('Transit')
            ->assertSee('Lune')
            ->assertSee('Capture historique M31')
            ->assertSee(route('admin.astrophotos.edit', $photo), false)
            ->assertSee('Rig Principal')
            ->assertSee('Maison')
            ->assertSee('Acquisition conseillée')
            ->assertSee('Sous-pose')
            ->assertSee('Filtres')
            ->assertSee('Night Planner')
            ->assertSee('Ajouter à la proposition');
    }

    public function test_target_detail_can_add_target_to_planner_draft_and_saved_plan(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $target = CelestialObject::factory()->create([
            'catalog_number' => 'ADD-ME',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 5,
        ]);
        CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => 'SEED',
            'ra_deg' => 40.0,
            'dec_deg' => 88.0,
            'magnitude' => 4,
            'angular_width_arcmin' => 60,
            'angular_height_arcmin' => 60,
        ]);

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.store'), ['date' => '2026-08-03'])
            ->assertRedirect();

        $plan = ImagingPlan::query()->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.nightscope.targets.show', [
                'celestialObject' => $target,
                'date' => '2026-08-03',
            ]))
            ->assertOk()
            ->assertSee('Plans sauvegardés ce jour')
            ->assertSee('Plan #'.$plan->id);

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.draft.targets'), [
                'date' => '2026-08-03',
                'celestial_object_id' => $target->id,
                'planned_start' => '22:10',
                'planned_end' => '23:10',
            ])
            ->assertRedirect(route('admin.nightscope.planner', ['date' => '2026-08-03']));

        $this->actingAs($user)
            ->get(route('admin.nightscope.planner', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('ADD-ME');

        $this->actingAs($user)
            ->from(route('admin.nightscope.targets.show', ['celestialObject' => $target, 'date' => '2026-08-03']))
            ->post(route('admin.nightscope.plans.targets.store', $plan), [
                'celestial_object_id' => $target->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('imaging_plan_targets', [
            'imaging_plan_id' => $plan->id,
            'celestial_object_id' => $target->id,
        ]);
    }

    public function test_target_detail_can_switch_rig_and_location(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $defaultLocation = ObservingLocation::factory()->default()->create([
            'name' => 'Maison',
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $secondaryLocation = ObservingLocation::factory()->create([
            'name' => 'Site sombre',
            'latitude' => 48.0,
            'longitude' => -2.0,
            'timezone' => 'Europe/Paris',
            'is_default' => false,
        ]);
        $defaultRig = EquipmentSetup::factory()->default()->create(['name' => 'Rig Principal']);
        $secondaryRig = EquipmentSetup::factory()->create(['name' => 'Rig Secondaire', 'is_default' => false]);
        $target = CelestialObject::factory()->create([
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
        ]);

        foreach ([$defaultRig, $secondaryRig] as $rig) {
            $tube = SetupItem::factory()->create([
                'type' => SetupItemType::OpticalTube,
                'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
            ]);
            $camera = SetupItem::factory()->create([
                'type' => SetupItemType::Camera,
                'specs' => SetupItemSpecs::from(['sensor_width_mm' => 11.31, 'sensor_height_mm' => 11.31, 'pixel_size_um' => 3.76]),
            ]);
            $rig->setupItems()->attach([$tube->id => ['enabled' => true], $camera->id => ['enabled' => true]]);
        }

        $this->actingAs($user)
            ->get(route('admin.nightscope.targets.show', [
                'celestialObject' => $target,
                'date' => '2026-08-03',
                'observing_location_id' => $secondaryLocation->id,
                'equipment_setup_id' => $secondaryRig->id,
            ]))
            ->assertOk()
            ->assertSee('Site sombre')
            ->assertSee('Rig Secondaire')
            ->assertSee('value="'.$secondaryLocation->id.'" selected', false)
            ->assertSee('value="'.$secondaryRig->id.'" selected', false)
            ->assertDontSee('value="'.$defaultLocation->id.'" selected', false);
    }

    public function test_target_detail_can_force_mosaic_overlay(): void
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
            'angular_width_arcmin' => 10,
            'angular_height_arcmin' => 8,
        ]);
        $rig = EquipmentSetup::factory()->default()->create(['name' => 'Rig Principal']);
        $tube = SetupItem::factory()->create([
            'type' => SetupItemType::OpticalTube,
            'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
        ]);
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['sensor_width_mm' => 11.31, 'sensor_height_mm' => 11.31, 'pixel_size_um' => 3.76]),
        ]);
        $rig->setupItems()->attach([$tube->id => ['enabled' => true], $camera->id => ['enabled' => true]]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.targets.show', [
                'celestialObject' => $target,
                'date' => '2026-08-03',
                'mosaic' => '2x2',
            ]))
            ->assertOk()
            ->assertSee('Mosaïque')
            ->assertSee('Mosaïque 2×2')
            ->assertSee('value="2x2" selected', false)
            ->assertSee('forcée');
    }
}
