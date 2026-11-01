<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\ObservingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AstrophotoLocationPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
        Storage::fake('s3');
    }

    public function test_new_photo_uses_an_observing_location_but_only_its_region_is_public(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $location = ObservingLocation::factory()->default()->create([
            'name' => 'Maison très privée XYZ',
            'region' => 'Loire-Atlantique publique XYZ',
            'latitude' => 47.1234567,
            'longitude' => -1.7654321,
        ]);

        $this->actingAs($user)->post(route('admin.astrophotos.store'), [
            'title' => 'Photo avec région',
            'captured_at' => '2026-08-03',
            'observing_location_id' => $location->id,
            'image' => UploadedFile::fake()->image('region.jpg', 1200, 800),
        ])->assertRedirect(route('admin.astrophotos.index'));

        $photo = Astrophoto::query()->firstOrFail();
        $this->assertTrue($photo->observingLocation->is($location));
        $this->assertFalse($photo->is_published);
        $photo->update(['is_published' => true]);

        $this->withoutVite()->get(route('welcome'))
            ->assertOk()
            ->assertSee('Loire-Atlantique publique XYZ')
            ->assertDontSee('Maison très privée XYZ')
            ->assertDontSee('47.1234567')
            ->assertDontSee('-1.7654321');

        $this->withoutVite()->get(route('astrophoto.full', $photo))
            ->assertOk()
            ->assertSee('Loire-Atlantique publique XYZ')
            ->assertDontSee('Maison très privée XYZ');
    }

    public function test_legacy_text_location_remains_private_and_is_not_publicly_searchable(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $photo = Astrophoto::factory()->create([
            'title' => 'Ancienne photo privée',
            'location_tag' => 'Adresse historique secrète XYZ',
            'observing_location_id' => null,
        ]);

        $this->withoutVite()->get(route('welcome'))
            ->assertOk()
            ->assertDontSee('Adresse historique secrète XYZ');

        $this->withoutVite()->get(route('welcome', ['search' => 'Adresse historique secrète XYZ']))
            ->assertOk()
            ->assertDontSee('Ancienne photo privée');

        $this->actingAs($user)->get(route('admin.astrophotos.edit', $photo))
            ->assertOk()
            ->assertSee('Ancien lieu texte conservé en privé')
            ->assertSee('Adresse historique secrète XYZ');
    }

    public function test_deleting_a_location_preserves_the_photo_and_clears_its_relation(): void
    {
        $location = ObservingLocation::factory()->create();
        $photo = Astrophoto::factory()->atObservingLocation($location)->create();

        $location->delete();

        $this->assertModelExists($photo);
        $this->assertNull($photo->fresh()->observing_location_id);
    }
}
