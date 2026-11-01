<?php

namespace Tests\Feature;

use App\Enums\SetupItemType;
use App\Models\Astrophoto;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupItemUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_uses_include_photos_taken_with_a_rig_containing_the_item(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $camera = SetupItem::factory()->create(['type' => SetupItemType::Camera, 'name' => 'ASI2600MC Pro']);
        $rig = EquipmentSetup::factory()->create();
        $rig->setupItems()->attach($camera, ['role' => null, 'enabled' => true]);
        $rigWithoutCamera = EquipmentSetup::factory()->create();
        $rigWithoutCamera->setupItems()->attach($camera, ['role' => null, 'enabled' => false]);

        Astrophoto::factory()->create(['equipment_setup_id' => $rig->id, 'camera_id' => null]);
        Astrophoto::factory()->create(['equipment_setup_id' => $rig->id, 'camera_id' => $camera->id]);
        Astrophoto::factory()->create(['equipment_setup_id' => null, 'camera_id' => $camera->id]);
        Astrophoto::factory()->create(['equipment_setup_id' => $rigWithoutCamera->id, 'camera_id' => null]);

        $this->assertSame(3, (int) SetupItem::query()->select('setup_items.*')->withPhotoUsesCount()->find($camera->id)->photo_uses_count);

        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->get(route('admin.equipment.index'))
            ->assertOk()
            ->assertSee('3 photos · 2 rigs');
    }
}
