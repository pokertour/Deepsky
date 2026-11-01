<?php

namespace Tests\Feature;

use App\Data\Equipment\EquipmentOptics;
use App\Data\Equipment\SetupItemSpecs;
use App\Enums\SetupItemType;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ObservingLocation;
use App\Models\SetupItem;
use App\Models\User;
use App\Services\Planning\ExternalReferencePhotosFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExternalReferencePhotosFinderTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_maps_wikimedia_commons_images(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        1 => [
                            'title' => 'File:NGC 3031 Bode.jpg',
                            'imageinfo' => [[
                                'mime' => 'image/jpeg',
                                'thumburl' => 'https://upload.wikimedia.org/thumb/bode.jpg',
                                'url' => 'https://upload.wikimedia.org/bode.jpg',
                                'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:NGC_3031_Bode.jpg',
                                'extmetadata' => [
                                    'ObjectName' => ['value' => 'Bode\'s Galaxy'],
                                    'Artist' => ['value' => '<b>Alice</b>'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        $target = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => '3031',
            'alternative_names' => ['M 81'],
        ]);

        $results = app(ExternalReferencePhotosFinder::class)->find($target);

        $this->assertCount(1, $results);
        $this->assertSame('Bode\'s Galaxy', $results[0]->title);
        $this->assertSame('Alice', $results[0]->author);
        $this->assertSame('Wikimedia Commons', $results[0]->matchLabel);
    }

    public function test_astrobin_search_url_includes_focal_length(): void
    {
        $target = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => '3031',
            'alternative_names' => ['M 81'],
        ]);
        $optics = new EquipmentOptics(
            nativeFocalLengthMm: 750,
            effectiveFocalLengthMm: 750,
            apertureMm: 150,
            focalRatio: 5,
            sensorWidthMm: 11.31,
            sensorHeightMm: 11.31,
            pixelSizeUm: 3.76,
            pixelScaleArcsec: 1.03,
            horizontalFieldOfViewDeg: 0.86,
            verticalFieldOfViewDeg: 0.86,
            diagonalFieldOfViewDeg: 1.22,
        );

        $url = app(ExternalReferencePhotosFinder::class)->astroBinSearchUrl($target, $optics);

        $this->assertStringContainsString('astrobin.com/search', $url);
        $this->assertStringContainsString(rawurlencode('M81'), $url);
        $this->assertStringContainsString(rawurlencode('750mm'), $url);
    }

    public function test_target_detail_shows_commons_and_astrobin_link(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $target = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => '3031',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'angular_width_arcmin' => 20,
            'angular_height_arcmin' => 10,
            'alternative_names' => ['M 81'],
        ]);
        $rig = EquipmentSetup::factory()->default()->create(['name' => 'Rig Principal']);
        $tube = SetupItem::factory()->create([
            'type' => SetupItemType::OpticalTube,
            'brand' => 'SkyWatcher',
            'name' => '150P',
            'specs' => SetupItemSpecs::from(['focal_length_mm' => 750, 'aperture_mm' => 150]),
        ]);
        $camera = SetupItem::factory()->create([
            'type' => SetupItemType::Camera,
            'specs' => SetupItemSpecs::from(['sensor_width_mm' => 11.31, 'sensor_height_mm' => 11.31, 'pixel_size_um' => 3.76]),
        ]);
        $rig->setupItems()->attach([$tube->id => ['enabled' => true], $camera->id => ['enabled' => true]]);

        Http::fake([
            'commons.wikimedia.org/*' => Http::response([
                'query' => [
                    'pages' => [
                        1 => [
                            'title' => 'File:M81.jpg',
                            'imageinfo' => [[
                                'mime' => 'image/jpeg',
                                'thumburl' => 'https://upload.wikimedia.org/thumb/m81.jpg',
                                'descriptionurl' => 'https://commons.wikimedia.org/wiki/File:M81.jpg',
                                'extmetadata' => [
                                    'ObjectName' => ['value' => 'M81 externe'],
                                    'Artist' => ['value' => 'Carol'],
                                ],
                            ]],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.targets.show', [
                'celestialObject' => $target,
                'date' => '2026-08-03',
            ]))
            ->assertOk()
            ->assertSee('Références externes')
            ->assertSee('M81 externe')
            ->assertSee('AstroBin')
            ->assertSee('astrobin.com/search', false)
            ->assertSee('750');
    }
}
