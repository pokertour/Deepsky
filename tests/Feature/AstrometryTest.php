<?php

namespace Tests\Feature;

use App\Enums\AstrometryStatus;
use App\Jobs\EnrichFieldDistancesJob;
use App\Jobs\SolveAstrophotoJob;
use App\Listeners\DispatchAstrometrySolve;
use App\Models\Astrophoto;
use App\Services\AstrometryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Events\MediaHasBeenAddedEvent;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class AstrometryTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_session_key(): void
    {
        config(['services.astrometry.key' => 'my-api-key']);

        Http::fake([
            'nova.astrometry.net/api/login' => Http::response(['status' => 'success', 'session' => 'sess-123']),
        ]);

        $this->assertSame('sess-123', (new AstrometryService)->login());
    }

    public function test_login_throws_without_api_key(): void
    {
        config(['services.astrometry.key' => null]);

        $this->expectException(RuntimeException::class);

        (new AstrometryService)->login();
    }

    public function test_submit_file_returns_submission_id(): void
    {
        Http::fake([
            'nova.astrometry.net/api/upload' => Http::response(['status' => 'success', 'subid' => 4242]),
        ]);

        $this->assertSame(4242, (new AstrometryService)->submitFile('sess-123', 'fake-image-contents', 'photo.webp'));
    }

    public function test_job_fails_when_submission_errors_server_side(): void
    {
        $photo = Astrophoto::factory()->create([
            'astrometry_submission_id' => 4242,
            'astrometry_status' => AstrometryStatus::Solving,
        ]);

        Http::fake([
            'nova.astrometry.net/api/submissions/4242' => Http::response([
                'jobs' => [],
                'error_message' => '403 Client Error: Forbidden',
            ]),
        ]);

        (new SolveAstrophotoJob($photo))->handle(new AstrometryService);

        $this->assertSame(AstrometryStatus::Failed, $photo->refresh()->astrometry_status);
    }

    public function test_job_stores_calibration_when_solve_succeeds(): void
    {
        $photo = Astrophoto::factory()->create([
            'astrometry_submission_id' => 4242,
            'astrometry_status' => AstrometryStatus::Solving,
            'tags' => ['widefield'],
        ]);

        $simbadBody = "::data::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::\n\nM  81|NAME Bode's Galaxy|Seyfert2\n";

        Http::fake([
            'nova.astrometry.net/api/jobs/777/annotations/*' => Http::response([
                'annotations' => [
                    ['type' => 'ngc', 'names' => ['NGC 3031'], 'pixelx' => 1098.98, 'pixely' => 665.31, 'radius' => 197.4519],
                    ['type' => 'hd', 'names' => ['HD 110464'], 'pixelx' => 1129.93, 'pixely' => 138.7, 'radius' => 0.0],
                ],
            ]),
            'simbad.cds.unistra.fr/*' => Http::response($simbadBody),
            'nova.astrometry.net/api/submissions/4242' => Http::response(['jobs' => [777]]),
            'nova.astrometry.net/api/jobs/777' => Http::response(['status' => 'success']),
            'nova.astrometry.net/api/jobs/777/info/*' => Http::response([
                'status' => 'success',
                'calibration' => [
                    'ra' => 148.888,
                    'dec' => 69.065,
                    'pixscale' => 1.85,
                    'radius' => 1.2,
                    'orientation' => 92.4,
                ],
                'objects_in_field' => ['M 81', 'M 82', "Bode's Galaxy"],
            ]),
        ]);

        Bus::fake([EnrichFieldDistancesJob::class]);

        (new SolveAstrophotoJob($photo))->handle(new AstrometryService);

        $photo->refresh();

        Bus::assertDispatched(EnrichFieldDistancesJob::class, fn (EnrichFieldDistancesJob $job): bool => $job->astrophoto->is($photo));
        $this->assertSame(AstrometryStatus::Solved, $photo->astrometry_status);
        $this->assertEqualsWithDelta(148.888, $photo->ra, 0.001);
        $this->assertEqualsWithDelta(69.065, $photo->dec, 0.001);
        $this->assertEqualsWithDelta(1.85, $photo->pixel_scale, 0.001);
        $this->assertSame(['M 81', 'M 82', "Bode's Galaxy"], $photo->objects_in_field);
        $this->assertNotNull($photo->astrometry_solved_at);
        $this->assertSame(['widefield', 'galaxy'], $photo->tags);
        $this->assertEquals(
            [['name' => 'NGC 3031', 'x' => 1099.0, 'y' => 665.3, 'r' => 197.5]],
            $photo->annotations,
        );
    }

    public function test_job_marks_photo_as_failed_when_solve_fails(): void
    {
        $photo = Astrophoto::factory()->create([
            'astrometry_submission_id' => 4242,
            'astrometry_status' => AstrometryStatus::Solving,
        ]);

        Http::fake([
            'nova.astrometry.net/api/submissions/4242' => Http::response(['jobs' => [777]]),
            'nova.astrometry.net/api/jobs/777' => Http::response(['status' => 'failure']),
        ]);

        (new SolveAstrophotoJob($photo))->handle(new AstrometryService);

        $this->assertSame(AstrometryStatus::Failed, $photo->refresh()->astrometry_status);
    }

    public function test_job_marks_photo_as_failed_without_media(): void
    {
        config(['services.astrometry.key' => 'my-api-key']);

        $photo = Astrophoto::factory()->create([
            'astrometry_status' => AstrometryStatus::Pending,
        ]);

        (new SolveAstrophotoJob($photo))->handle(new AstrometryService);

        $this->assertSame(AstrometryStatus::Failed, $photo->refresh()->astrometry_status);
    }

    public function test_adding_media_dispatches_solve_job_automatically(): void
    {
        config(['services.astrometry.key' => 'my-api-key']);
        Bus::fake();

        $photo = Astrophoto::factory()->create();
        $media = $this->makeMediaFor($photo, 'astros');

        (new DispatchAstrometrySolve)->handle(new MediaHasBeenAddedEvent($media));

        Bus::assertDispatched(SolveAstrophotoJob::class);
        $this->assertSame(AstrometryStatus::Pending, $photo->refresh()->astrometry_status);
    }

    public function test_adding_media_does_not_dispatch_without_api_key(): void
    {
        config(['services.astrometry.key' => null]);
        Bus::fake();

        $photo = Astrophoto::factory()->create();
        $media = $this->makeMediaFor($photo, 'astros');

        (new DispatchAstrometrySolve)->handle(new MediaHasBeenAddedEvent($media));

        Bus::assertNotDispatched(SolveAstrophotoJob::class);
        $this->assertSame(AstrometryStatus::Idle, $photo->refresh()->astrometry_status);
    }

    private function makeMediaFor(Astrophoto $photo, string $collection): Media
    {
        return $photo->media()->create([
            'collection_name' => $collection,
            'name' => 'photo',
            'file_name' => 'photo.webp',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);
    }

    public function test_formatted_coordinates_accessors(): void
    {
        $photo = Astrophoto::factory()->make([
            'ra' => 148.888,
            'dec' => -69.065,
        ]);

        $this->assertSame('09h 55m 33.1s', $photo->formatted_ra);
        $this->assertSame('-69° 03′ 54.0″', $photo->formatted_dec);
    }
}
