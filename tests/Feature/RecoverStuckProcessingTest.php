<?php

namespace Tests\Feature;

use App\Enums\AstrometryStatus;
use App\Enums\ConversionStatus;
use App\Jobs\PerformAstrophotoConversionsJob;
use App\Models\Astrophoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class RecoverStuckProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function photoWithQueuedImage(int $hoursAgo): Astrophoto
    {
        $photo = Astrophoto::factory()->create();
        $media = $photo->addMedia(UploadedFile::fake()->image('m31.jpg', 100, 100))
            ->withCustomProperties(['processing_status' => ConversionStatus::Queued->value])
            ->toMediaCollection('astros', 's3');
        Media::query()->where('id', $media->id)->update(['updated_at' => now()->subHours($hoursAgo)]);

        return $photo;
    }

    public function test_orphaned_queued_conversions_are_requeued_but_recent_ones_are_left_alone(): void
    {
        Storage::fake('s3');
        Queue::fake();
        $orphaned = $this->photoWithQueuedImage(hoursAgo: 8);
        $recent = $this->photoWithQueuedImage(hoursAgo: 1);
        Queue::fake();

        $this->artisan('maintenance:recover-processing')
            ->expectsOutputToContain('1 conversion(s) orpheline(s) relancée(s)')
            ->assertSuccessful();

        Queue::assertPushed(PerformAstrophotoConversionsJob::class, 1);
        $this->assertTrue($orphaned->getFirstMedia('astros')->fresh()->updated_at->isAfter(now()->subMinute()));
        $this->assertTrue($recent->getFirstMedia('astros')->fresh()->updated_at->isBefore(now()->subMinutes(30)));

        Queue::fake();
        $this->artisan('maintenance:recover-processing')
            ->expectsOutputToContain('0 conversion(s) orpheline(s) relancée(s)')
            ->assertSuccessful();
        Queue::assertNothingPushed();
    }

    public function test_stuck_astrometry_is_marked_failed_so_it_can_be_relaunched(): void
    {
        $stuck = Astrophoto::factory()->create(['astrometry_status' => AstrometryStatus::Solving]);
        $running = Astrophoto::factory()->create(['astrometry_status' => AstrometryStatus::Pending]);
        Astrophoto::query()->where('id', $stuck->id)->update(['updated_at' => now()->subHours(13)]);

        $this->artisan('maintenance:recover-processing')->assertSuccessful();

        $this->assertSame(AstrometryStatus::Failed, $stuck->fresh()->astrometry_status);
        $this->assertSame(AstrometryStatus::Pending, $running->fresh()->astrometry_status);
    }

    public function test_old_temporary_conversion_directories_are_removed(): void
    {
        $root = storage_path('framework/testing/media-temp');
        config(['media-library.temporary_directory_path' => $root]);
        File::ensureDirectoryExists("{$root}/old");
        File::ensureDirectoryExists("{$root}/recent");
        touch("{$root}/old", now()->subDays(2)->getTimestamp());

        $this->artisan('maintenance:recover-processing')
            ->expectsOutputToContain('1 répertoire(s) temporaire(s) supprimé(s)')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist("{$root}/old");
        $this->assertDirectoryExists("{$root}/recent");
        File::deleteDirectory($root);
    }
}
