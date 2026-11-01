<?php

namespace Tests\Feature;

use App\Models\CelestialObject;
use App\Models\User;
use App\Services\Astronomy\CelestialPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CelestialPreviewProxyTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_proxy_fetches_from_cds_and_falls_back_to_mirror(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $user = User::factory()->create(['email' => 'owner@example.com']);

        Http::preventStrayRequests();
        Http::fake([
            'alasky.cds.unistra.fr/*' => Http::response('down', 503),
            'alaskybis.cds.unistra.fr/*' => Http::response('fake-jpeg-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.preview', [
                'ra' => 341.837542,
                'dec' => 58.132417,
                'fov' => 2.021,
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg')
            ->assertSee('fake-jpeg-bytes', false);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'hips=CDS/P/DSS2/color'));
    }

    public function test_celestial_preview_builds_local_proxy_url(): void
    {
        $url = app(CelestialPreview::class)->url(new CelestialObject([
            'ra_deg' => 10.5,
            'dec_deg' => -20.25,
            'angular_width_arcmin' => 30,
            'angular_height_arcmin' => 20,
        ]));

        $this->assertStringContainsString('/admin/nightscope/preview', $url);
        $this->assertStringContainsString('ra=10.5', $url);
        $this->assertStringContainsString('dec=-20.25', $url);
    }

    public function test_preview_falls_back_to_skyview_in_colour_and_skips_cds_while_it_is_down(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Http::fake([
            'alasky.cds.unistra.fr/*' => Http::response('down', 503),
            'alaskybis.cds.unistra.fr/*' => Http::response('down', 504),
            'skyview.gsfc.nasa.gov/*' => fn (Request $request) => Http::response(
                $this->plate(str_contains($request->url(), 'DSS2%20Red') ? 200 : 60),
                200,
                ['Content-Type' => 'image/jpeg'],
            ),
        ]);

        $response = $this->actingAs($user)
            ->get(route('admin.nightscope.preview', ['ra' => 83.82, 'dec' => -5.39, 'fov' => 1.5]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $image = imagecreatefromstring($response->getContent());
        $this->assertSame([640, 360], [imagesx($image), imagesy($image)]);
        $pixel = imagecolorat($image, 320, 180);
        $this->assertGreaterThan($pixel & 0xFF, ($pixel >> 16) & 0xFF, 'Plaque rouge plus brillante : le centre doit tirer vers le rouge.');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'Survey=DSS2%20Blue')
            && str_contains($request->url(), 'Position=83.82%2C-5.39')
            && str_contains($request->url(), 'Pixels=640%2C360'));

        $this->get(route('admin.nightscope.preview', ['ra' => 10.68, 'dec' => 41.27, 'fov' => 2]))->assertOk();

        Http::assertSentCount(6);
    }

    public function test_preview_is_unavailable_when_no_survey_covers_the_field(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $noData = imagecreatetruecolor(200, 150);
        ob_start();
        imagejpeg($noData);
        $noDataJpeg = (string) ob_get_clean();
        Http::fake([
            'alasky*.cds.unistra.fr/*' => Http::response('down', 503),
            'skyview.gsfc.nasa.gov/*' => Http::response($noDataJpeg, 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.preview', ['ra' => 83.82, 'dec' => -5.39, 'fov' => 1.5]))
            ->assertStatus(502);
    }

    /** Plaque DSS2 factice : fond sombre et objet central de la luminosité donnée. */
    private function plate(int $centreBrightness): string
    {
        $image = imagecreatetruecolor(640, 360);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 20, 20));
        imagefilledellipse($image, 320, 180, 200, 120, imagecolorallocate($image, $centreBrightness, $centreBrightness, $centreBrightness));
        ob_start();
        imagejpeg($image, quality: 95);

        return (string) ob_get_clean();
    }
}
