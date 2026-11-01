<?php

namespace Tests\Unit;

use App\Models\CelestialObject;
use App\Services\Astronomy\CelestialPreview;
use App\Services\Astronomy\DssColorComposer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CelestialPreviewTest extends TestCase
{
    #[DataProvider('fieldOfViewTargets')]
    public function test_preview_url_uses_target_coordinates_and_a_bounded_field_of_view(
        ?float $widthArcmin,
        ?float $heightArcmin,
        float $expectedFieldOfView,
    ): void {
        $target = new CelestialObject([
            'ra_deg' => 201.365,
            'dec_deg' => -43.019,
            'angular_width_arcmin' => $widthArcmin,
            'angular_height_arcmin' => $heightArcmin,
        ]);

        $preview = new CelestialPreview(new DssColorComposer);
        $url = $preview->url($target);
        $this->assertSame(parse_url(route('admin.nightscope.preview'), PHP_URL_PATH), parse_url($url, PHP_URL_PATH));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('201.365', $query['ra']);
        $this->assertSame('-43.019', $query['dec']);
        $this->assertSame((string) $expectedFieldOfView, $query['fov']);
        parse_str((string) parse_url($preview->upstreamUrls(201.365, -43.019, $expectedFieldOfView)[0], PHP_URL_QUERY), $upstream);
        $this->assertSame('CDS/P/DSS2/color', $upstream['hips']);
        $this->assertSame('jpg', $upstream['format']);
    }

    public static function fieldOfViewTargets(): array
    {
        return [
            'unknown size uses minimum' => [null, null, 0.5],
            'known size receives breathing room' => [60, 30, 1.5],
            'very large target uses maximum' => [360, 120, 4.0],
        ];
    }
}
