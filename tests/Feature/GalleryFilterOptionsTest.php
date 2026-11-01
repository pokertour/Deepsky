<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Services\GalleryFilterOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GalleryFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_only_include_published_photos(): void
    {
        $rig = EquipmentSetup::factory()->create(['name' => 'Rig publié']);
        Astrophoto::factory()->withEquipmentSetup($rig)->create([
            'captured_at' => '2025-03-10',
            'celestial_object_id' => CelestialObject::factory()->create(['constellation' => 'Andromeda'])->id,
        ]);
        Astrophoto::factory()->withEquipmentSetup(EquipmentSetup::factory()->create(['name' => 'Rig brouillon']))->create([
            'is_published' => false,
            'captured_at' => '2019-01-01',
            'celestial_object_id' => CelestialObject::factory()->create(['constellation' => 'Orion'])->id,
        ]);

        $options = app(GalleryFilterOptions::class)->all();

        $this->assertSame(['Andromeda'], $options['constellations']);
        $this->assertSame([2025], $options['years']);
        $this->assertSame([['id' => $rig->id, 'name' => 'Rig publié']], $options['rigs']);
    }

    public function test_options_are_cached_and_flushed_when_a_photo_is_saved(): void
    {
        $photo = Astrophoto::factory()->create(['captured_at' => '2024-05-01']);
        $this->assertSame([2024], app(GalleryFilterOptions::class)->all()['years']);
        $this->assertTrue(Cache::has(GalleryFilterOptions::CACHE_KEY));

        $photo->update(['captured_at' => '2026-05-01']);

        $this->assertFalse(Cache::has(GalleryFilterOptions::CACHE_KEY));
        $this->assertSame([2026], app(GalleryFilterOptions::class)->all()['years']);
    }

    public function test_cache_is_flushed_when_a_photo_is_deleted(): void
    {
        $photo = Astrophoto::factory()->create();
        app(GalleryFilterOptions::class)->all();

        $photo->delete();

        $this->assertFalse(Cache::has(GalleryFilterOptions::CACHE_KEY));
    }

    public function test_views_and_likes_do_not_flush_the_cache(): void
    {
        $photo = Astrophoto::factory()->create();
        app(GalleryFilterOptions::class)->all();

        $photo->increment('views_count');
        $photo->increment('likes_count');

        $this->assertTrue(Cache::has(GalleryFilterOptions::CACHE_KEY));
    }

    public function test_gallery_renders_cached_filter_options(): void
    {
        $rig = EquipmentSetup::factory()->create(['name' => 'Rig Galerie']);
        Astrophoto::factory()->withEquipmentSetup($rig)->create(['captured_at' => '2023-08-12']);

        $this->withoutVite()->get(route('welcome'))
            ->assertOk()
            ->assertSee('value="rig:'.$rig->id.'"', false)
            ->assertSee('Rig Galerie')
            ->assertSee('<option value="2023"', false);
    }
}
