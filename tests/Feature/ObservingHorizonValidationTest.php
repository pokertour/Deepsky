<?php

namespace Tests\Feature;

use App\Models\ObservingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservingHorizonValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com']]);
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']));
    }

    public function test_decimal_horizon_points_are_preserved_on_create_and_update(): void
    {
        $this->post(route('admin.observing-locations.store'), [
            'name' => 'Terrain', 'latitude' => 47, 'longitude' => 1, 'timezone' => 'Europe/Paris',
            'horizon_mask' => '{"90.25":12.5,"90.75":22.5}',
        ])->assertSessionDoesntHaveErrors()->assertRedirect();
        $location = ObservingLocation::query()->sole();
        $this->assertSame(12.5, $location->horizon_mask['90.25']);
        $this->assertSame(22.5, $location->horizon_mask['90.75']);
        $this->put(route('admin.observing-locations.update', $location), [
            'name' => 'Terrain', 'latitude' => 47, 'longitude' => 1, 'timezone' => 'Europe/Paris',
            'horizon_mask' => '{"360":10}',
        ])->assertSessionHasErrors('horizon_mask');
        $this->assertSame(12.5, $location->fresh()->horizon_mask['90.25']);
    }

    public function test_json_list_cannot_be_saved_as_horizon_object(): void
    {
        $this->post(route('admin.observing-locations.store'), [
            'name' => 'Terrain', 'latitude' => 47, 'longitude' => 1, 'timezone' => 'Europe/Paris',
            'horizon_mask' => '[10,20,30]',
        ])->assertSessionHasErrors('horizon_mask');
        $this->assertSame(0, ObservingLocation::query()->count());
    }

    public function test_timezone_must_match_the_longitude(): void
    {
        $this->from(route('admin.observing-locations.create'))->post(route('admin.observing-locations.store'), [
            'name' => 'Bahamas', 'latitude' => 26.2, 'longitude' => -76.86, 'timezone' => 'Europe/Paris',
        ])->assertSessionHasErrors('timezone')->assertSessionHasInput('name', 'Bahamas');

        $this->post(route('admin.observing-locations.store'), [
            'name' => 'Bahamas', 'latitude' => 26.2, 'longitude' => -76.86, 'timezone' => 'America/Nassau',
        ])->assertSessionDoesntHaveErrors();

        $this->post(route('admin.observing-locations.store'), [
            'name' => 'Galice', 'latitude' => 42.9, 'longitude' => -8.5, 'timezone' => 'Europe/Madrid',
        ])->assertSessionDoesntHaveErrors();
    }
}
