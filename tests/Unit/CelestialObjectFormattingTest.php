<?php

namespace Tests\Unit;

use App\Models\CelestialObject;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CelestialObjectFormattingTest extends TestCase
{
    #[DataProvider('distanceFormattingProvider')]
    public function test_it_formats_distance_for_public_display(?float $distanceLy, ?string $expected): void
    {
        $object = new CelestialObject(['distance_ly' => $distanceLy]);

        $this->assertSame($expected, $object->formattedDistance());
    }

    public static function distanceFormattingProvider(): array
    {
        return [
            'missing' => [null, null],
            'small' => [1344.0, '1 344 al'],
            'thousands' => [45_000.0, '45,0 k al'],
            'millions' => [2_540_000.0, '2,54 M al'],
        ];
    }

    #[DataProvider('physicalSizeProvider')]
    public function test_it_derives_physical_size_from_distance_and_major_axis(array $attributes, ?string $expected): void
    {
        $object = new CelestialObject($attributes);

        $this->assertSame($expected, $object->formattedPhysicalSize());
    }

    public static function physicalSizeProvider(): array
    {
        return [
            'andromeda uses the major axis' => [['distance_ly' => 2_540_000.0, 'angular_width_arcmin' => 60.0, 'angular_height_arcmin' => 190.0], '≈ 140,4 k al'],
            'orion nebula' => [['distance_ly' => 1344.0, 'angular_width_arcmin' => 85.0], '≈ 33 al'],
            'ring nebula below ten light years' => [['distance_ly' => 2283.0, 'angular_width_arcmin' => 1.4, 'angular_height_arcmin' => 1.0], '≈ 0,9 al'],
            'missing distance' => [['angular_width_arcmin' => 85.0], null],
            'missing angular size' => [['distance_ly' => 1344.0], null],
        ];
    }

    public function test_it_formats_angular_size(): void
    {
        $object = new CelestialObject([
            'angular_width_arcmin' => 190.0,
            'angular_height_arcmin' => 60.5,
        ]);

        $this->assertSame('190,0′ × 60,5′', $object->formattedAngularSize());
    }

    public function test_it_maps_type_to_gallery_tag_and_photo_title(): void
    {
        $named = new CelestialObject([
            'catalog' => 'M',
            'catalog_number' => '81',
            'name' => "Bode's Galaxy",
            'type' => 'galaxy',
        ]);

        $this->assertSame('galaxy', $named->galleryTag());
        $this->assertSame("Bode's Galaxy", $named->suggestedPhotoTitle());

        $unnamed = new CelestialObject([
            'catalog' => 'NGC',
            'catalog_number' => '7000',
            'name' => null,
            'type' => 'nebula',
        ]);

        $this->assertSame('nebula', $unnamed->galleryTag());
        $this->assertSame('NGC 7000', $unnamed->suggestedPhotoTitle());
    }
}
