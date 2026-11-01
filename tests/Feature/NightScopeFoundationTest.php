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
use App\Services\Astronomy\OpenNgcCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NightScopeFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    public function test_admin_can_store_typed_setup_item_specs(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)->post(route('admin.equipment.store'), [
            'name' => 'ASI533MC Pro',
            'brand' => 'ZWO',
            'type' => 'camera',
            'specs' => [
                'sensor_width_mm' => 11.31,
                'sensor_height_mm' => 11.31,
                'resolution_x' => 3008,
                'resolution_y' => 3008,
                'pixel_size_um' => 3.76,
                'cooled' => true,
                'factor' => 0.95,
            ],
        ])->assertRedirect(route('admin.equipment.index'));

        $item = SetupItem::query()->firstOrFail();

        $this->assertSame(SetupItemType::Camera, $item->type);
        $this->assertInstanceOf(SetupItemSpecs::class, $item->specs);
        $this->assertSame(3.76, $item->specs->pixelSizeUm());
        $this->assertTrue($item->specs->cooled());
        $this->assertNull($item->specs->factor());
    }

    public function test_sqlite_accepts_new_equipment_types(): void
    {
        $item = SetupItem::factory()->create(['type' => SetupItemType::Focuser]);

        $this->assertSame(SetupItemType::Focuser, $item->fresh()->type);
    }

    public function test_openngc_import_is_idempotent_and_creates_messier_and_ngc_objects(): void
    {
        $csv = <<<'CSV'
Name;Type;RA;Dec;Const;MajAx;MinAx;PosAng;B-Mag;V-Mag;J-Mag;H-Mag;K-Mag;SurfBr;Hubble;Pax;Pm-RA;Pm-Dec;RadVel;Redshift;Cstar U-Mag;Cstar B-Mag;Cstar V-Mag;M;NGC;IC;Cstar Names;Identifiers;Common names;NED notes;OpenNGC notes;Sources
NGC0224;G;00:42:44.35;+41:16:08.6;And;177.83;69.66;35;4.29;3.44;2.09;1.28;0.98;23.63;Sb;6.0000;;;-300;-0.001000;;;;031;;;;2MASX J00424433+4116074,PGC 002557;Andromeda Galaxy;;;OpenNGC
NGC7000;HII;20:59:17.14;+44:31:43.6;Cyg;120.00;30.00;;4.00;;;;;;;;;;;;;;;;;;;C 020,LBN 373;North America Nebula;;;OpenNGC
CSV;

        $importer = app(OpenNgcCatalogImporter::class);
        $first = $importer->import($csv);
        $second = $importer->import($csv);

        $this->assertSame(2, $first['imported']);
        $this->assertSame(2, $second['imported']);
        $this->assertSame(2, CelestialObject::query()->count());

        $andromeda = CelestialObject::query()->where('catalog', 'M')->where('catalog_number', '31')->firstOrFail();
        $this->assertEqualsWithDelta(10.6847917, $andromeda->ra_deg, 0.0000001);
        $this->assertContains('NGC 224', $andromeda->alternative_names);
        $this->assertSame('Andromeda Galaxy', $andromeda->name);

        $this->assertDatabaseHas('celestial_objects', [
            'catalog' => 'NGC',
            'catalog_number' => '7000',
            'type' => 'nebula',
        ]);
    }

    public function test_equipment_setup_syncs_items_and_keeps_one_default(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $camera = SetupItem::factory()->camera()->create();
        $mount = SetupItem::factory()->mount()->create();

        $this->actingAs($user)->post(route('admin.equipment-setups.store'), [
            'name' => 'Main Rig',
            'is_default' => true,
            'setup_item_ids' => [$camera->id, $mount->id],
            'roles' => [$camera->id => 'Imaging camera'],
        ])->assertRedirect(route('admin.equipment-setups.index'));

        $firstRig = EquipmentSetup::query()->firstOrFail();
        $this->assertTrue($firstRig->is_default);
        $this->assertCount(2, $firstRig->setupItems);
        $this->assertSame('Imaging camera', $firstRig->setupItems->firstWhere('id', $camera->id)->pivot->role);

        $this->actingAs($user)->post(route('admin.equipment-setups.store'), [
            'name' => 'Wide Field Rig',
            'is_default' => true,
            'setup_item_ids' => [$camera->id],
        ])->assertRedirect(route('admin.equipment-setups.index'));

        $this->assertFalse($firstRig->fresh()->is_default);
        $this->assertSame(1, EquipmentSetup::query()->where('is_default', true)->count());
    }

    public function test_observing_location_stores_horizon_mask_and_keeps_one_default(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create();

        $this->actingAs($user)->post(route('admin.observing-locations.store'), [
            'name' => 'Home',
            'latitude' => 47.218371,
            'longitude' => -1.553621,
            'altitude' => 35,
            'timezone' => 'Europe/Paris',
            'is_default' => true,
            'horizon_mask' => '{"0":25,"90":10,"180":5,"270":30}',
            'bortle_class' => 5,
        ])->assertRedirect(route('admin.observing-locations.index'));

        $this->actingAs($user)
            ->get(route('admin.observing-locations.create'))
            ->assertOk()
            ->assertSee('Ajouter un point')
            ->assertSee('Carte du lieu d’observation')
            ->assertSee('clinomètre', false)
            ->assertSee('Classe de Bortle');

        $location = ObservingLocation::query()->where('name', 'Home')->firstOrFail();
        $this->assertSame(25, $location->horizon_mask['0']);
        $this->assertSame(5, $location->bortle_class);
        $this->assertSame(1, ObservingLocation::query()->where('is_default', true)->count());
    }

    public function test_deleting_celestial_object_preserves_historical_astrophoto(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $object = CelestialObject::factory()->create(['catalog' => 'M', 'catalog_number' => '31']);
        $photo = Astrophoto::factory()->create([
            'target' => 'M 31',
            'celestial_object_id' => $object->id,
        ]);

        $this->actingAs($user)->delete(route('admin.celestial-objects.destroy', $object))->assertSessionHas('success');

        $this->assertModelExists($photo);
        $this->assertNull($photo->fresh()->celestial_object_id);
        $this->assertSame('M 31', $photo->fresh()->target);
    }

    public function test_astrophoto_rejects_unknown_equipment_setup(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $photo = Astrophoto::factory()->create();

        $this->actingAs($user)->put(route('admin.astrophotos.update', $photo), [
            'title' => $photo->title,
            'captured_at' => $photo->captured_at->format('Y-m-d'),
            'equipment_setup_id' => 999999,
        ])->assertSessionHasErrors('equipment_setup_id');
    }

    public function test_new_admin_sections_render_for_owner(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)->get(route('admin.equipment-setups.index'))->assertOk()->assertSee('Configurations matérielles');
        $this->actingAs($user)->get(route('admin.observing-locations.index'))->assertOk()->assertSee('NightScope');
        $this->actingAs($user)->get(route('admin.celestial-objects.index'))->assertOk()->assertSee('NightScope · Cibles');
    }
}
