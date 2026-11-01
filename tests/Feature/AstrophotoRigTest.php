<?php

namespace Tests\Feature;

use App\Enums\SetupItemType;
use App\Models\Astrophoto;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AstrophotoRigTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
        Storage::fake('s3');
    }

    public function test_admin_attaches_a_complete_rig_to_a_new_photo(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $rig = EquipmentSetup::factory()->default()->create(['name' => 'DeepSky Main Rig']);

        $this->actingAs($user)->post(route('admin.astrophotos.store'), [
            'title' => 'North America Nebula',
            'captured_at' => '2026-08-03',
            'equipment_setup_id' => $rig->id,
            'image' => UploadedFile::fake()->image('ngc7000.jpg', 1200, 800),
        ])->assertRedirect(route('admin.astrophotos.index'));

        $photo = Astrophoto::query()->firstOrFail();

        $this->assertTrue($photo->equipmentSetup->is($rig));
        $this->assertNull($photo->camera_id);
        $this->assertNull($photo->mount_id);
        $this->assertNull($photo->optical_tube_id);
    }

    public function test_default_rig_is_preselected_when_creating_a_photo(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $rig = EquipmentSetup::factory()->default()->create(['name' => 'Default Imaging Rig']);

        $this->actingAs($user)->get(route('admin.astrophotos.create'))
            ->assertOk()
            ->assertSee('Default Imaging Rig')
            ->assertSee('value="'.$rig->id.'" selected', false);
    }

    public function test_public_gallery_displays_all_enabled_rig_items_and_can_search_them(): void
    {
        $rig = EquipmentSetup::factory()->create(['name' => 'DeepSky Main Rig']);
        $tube = SetupItem::factory()->create(['type' => SetupItemType::OpticalTube, 'brand' => 'Sky-Watcher', 'name' => '150PDS']);
        $camera = SetupItem::factory()->create(['type' => SetupItemType::Camera, 'brand' => 'ZWO', 'name' => 'ASI533MC Pro']);
        $disabled = SetupItem::factory()->create(['type' => SetupItemType::Focuser, 'brand' => 'ZWO', 'name' => 'EAF Disabled']);
        $rig->setupItems()->attach([
            $tube->id => ['role' => 'Tube principal', 'position' => 1, 'enabled' => true],
            $camera->id => ['role' => 'Caméra principale', 'position' => 2, 'enabled' => true],
            $disabled->id => ['role' => null, 'position' => 3, 'enabled' => false],
        ]);
        Astrophoto::factory()->withEquipmentSetup($rig)->create(['title' => 'Rig Gallery Photo']);

        $this->withoutVite()->get(route('welcome'))
            ->assertOk()
            ->assertSee('DeepSky Main Rig')
            ->assertSee('Sky-Watcher 150PDS')
            ->assertSee('ZWO ASI533MC Pro')
            ->assertDontSee('EAF Disabled');

        $this->withoutVite()->get(route('welcome', ['search' => 'ASI533MC']))
            ->assertOk()
            ->assertSee('Rig Gallery Photo');
    }

    public function test_legacy_photo_still_displays_its_direct_equipment(): void
    {
        $tube = SetupItem::factory()->opticalTube()->create(['name' => 'Legacy Tube']);
        Astrophoto::factory()->create(['equipment_setup_id' => null, 'optical_tube_id' => $tube->id]);

        $this->withoutVite()->get(route('welcome'))
            ->assertOk()
            ->assertSee('Legacy Tube')
            ->assertSee('"rig":null');
    }

    public function test_a_rig_used_by_photos_cannot_be_deleted(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $rig = EquipmentSetup::factory()->create();
        Astrophoto::factory()->withEquipmentSetup($rig)->create();

        $this->actingAs($user)->delete(route('admin.equipment-setups.destroy', $rig))
            ->assertSessionHas('error');

        $this->assertModelExists($rig);
    }

    public function test_observatory_aggregates_the_optical_tube_from_the_rig(): void
    {
        $rig = EquipmentSetup::factory()->create();
        $tube = SetupItem::factory()->opticalTube()->create(['name' => 'Rig Optical Tube']);
        $rig->setupItems()->attach($tube, ['role' => null, 'enabled' => true]);
        Astrophoto::factory()->withEquipmentSetup($rig)->create();

        $this->get(route('observatory'))
            ->assertOk()
            ->assertSee('Rig Optical Tube');
    }
}
