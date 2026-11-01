<?php

namespace Tests\Feature;

use App\Models\CelestialObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CelestialObjectSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    public function test_admin_search_returns_fields_for_photo_autofill(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $object = CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => '81',
            'name' => "Bode's Galaxy",
            'type' => 'galaxy',
        ]);

        $this->actingAs($user)
            ->getJson(route('admin.celestial-objects.search', ['q' => 'M 81']))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $object->id,
                'label' => "M 81 — Bode's Galaxy",
                'designation' => 'M 81',
                'name' => "Bode's Galaxy",
                'title' => "Bode's Galaxy",
                'type' => 'galaxy',
                'tag' => 'galaxy',
            ]);
    }

    public function test_astrophoto_form_no_longer_exposes_simbad_lookup(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)
            ->withoutVite()
            ->get(route('admin.astrophotos.create'))
            ->assertOk()
            ->assertSee('Objet du catalogue')
            ->assertDontSee('SIMBAD')
            ->assertDontSee('/admin/simbad/lookup');
    }
}
