<?php

namespace Tests\Feature;

use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NightScopeWishlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com', 'other@example.com']]);
        $this->withoutVite();
    }

    public function test_favorites_are_personal_and_updates_preserve_existing_notes(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        $target = CelestialObject::factory()->create();
        $url = route('admin.nightscope.wishlist.update', $target);

        $this->actingAs($owner)->put($url, ['priority' => 1, 'note' => 'Projet personnel'])->assertRedirect();
        $this->put($url)->assertRedirect();
        $this->assertSame(1, $owner->celestialWishlistItems()->count());
        $this->assertSame('Projet personnel', $owner->celestialWishlistItems()->first()->note);
        $this->get(route('admin.nightscope.wishlist'))->assertOk()->assertSee('Projet personnel')->assertSee('Ajouter au Planner');
        $this->actingAs($other)->get(route('admin.nightscope.wishlist'))->assertOk()->assertDontSee('Projet personnel');
        $this->delete(route('admin.nightscope.wishlist.destroy', $target))->assertRedirect();
        $this->assertSame(1, $owner->celestialWishlistItems()->count());
        $this->actingAs($owner)->delete(route('admin.nightscope.wishlist.destroy', $target))->assertRedirect();
        $this->assertSame(0, $owner->celestialWishlistItems()->count());
    }

    public function test_wishlist_validates_priority_and_note(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $target = CelestialObject::factory()->create();
        $this->actingAs($owner)->put(route('admin.nightscope.wishlist.update', $target), [
            'priority' => 4, 'note' => str_repeat('a', 2001),
        ])->assertSessionHasErrors(['priority', 'note']);
        $this->assertSame(0, $owner->celestialWishlistItems()->count());
    }

    public function test_tonight_uses_selected_location_and_rig_and_can_explicitly_remove_rig(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create();
        $location = ObservingLocation::factory()->create(['name' => 'Site alternatif']);
        EquipmentSetup::factory()->create(['is_default' => true]);
        $setup = EquipmentSetup::factory()->create(['name' => 'Rig alternatif', 'is_default' => false]);
        $this->actingAs($owner)->get(route('admin.nightscope.tonight', [
            'date' => '2026-08-03', 'observing_location_id' => $location->id, 'equipment_setup_id' => $setup->id,
        ]))->assertOk()->assertViewHas('data', fn ($data) => $data->location->is($location) && $data->equipmentSetup->is($setup));
        $this->get(route('admin.nightscope.tonight', [
            'date' => '2026-08-03', 'equipment_setup_id' => '',
        ]))->assertOk()->assertViewHas('data', fn ($data) => $data->equipmentSetup === null);
    }

    public function test_tonight_favorites_filter_is_isolated_between_users_and_unfiltered_cache(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $other = User::factory()->create(['email' => 'other@example.com']);
        ObservingLocation::factory()->default()->create(['latitude' => 47.2, 'timezone' => 'Europe/Paris']);
        $target = CelestialObject::factory()->create(['type' => 'galaxy', 'dec_deg' => 89, 'ra_deg' => 38, 'magnitude' => 5]);
        $owner->celestialWishlistItems()->create(['celestial_object_id' => $target->id]);
        $url = route('admin.nightscope.tonight', ['date' => '2026-08-03', 'favorites_only' => 1]);
        $this->actingAs($owner)->get($url)->assertOk()->assertViewHas('data', fn ($data) => count($data->targets) === 1);
        $this->actingAs($other)->get($url)->assertOk()->assertViewHas('data', fn ($data) => $data->targets === []);
        $this->get(route('admin.nightscope.tonight', ['date' => '2026-08-03']))->assertOk()
            ->assertViewHas('data', fn ($data) => count($data->targets) === 1 && ! $data->favoritesOnly);
    }

    public function test_tonight_rejects_unknown_location_and_rig(): void
    {
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $this->actingAs($owner)->get(route('admin.nightscope.tonight', [
            'observing_location_id' => 999999, 'equipment_setup_id' => 999999,
        ]))->assertSessionHasErrors(['observing_location_id', 'equipment_setup_id']);
    }
}
