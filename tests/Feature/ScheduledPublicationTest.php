<?php

namespace Tests\Feature;

use App\Enums\ConversionStatus;
use App\Mail\NewAstrophotoPublished;
use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScheduledPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com'], 'admin.timezone' => 'Europe/Paris']);
        Storage::fake('s3');
        Queue::fake();
        Mail::fake();
        $this->travelTo('2026-11-02 10:00:00');
    }

    private function draft(bool $ready = true): Astrophoto
    {
        $photo = Astrophoto::factory()->create(['is_published' => false]);
        $media = $photo->addMedia(UploadedFile::fake()->image('m31.jpg', 100, 100))
            ->withCustomProperties(['processing_status' => ConversionStatus::Ready->value])->toMediaCollection('astros', 's3');

        if ($ready) {
            $media->generated_conversions = ['thumb' => true, 'optimized' => true, 'full_res' => true];
            $media->save();
        }

        return $photo->fresh();
    }

    public function test_publication_time_is_entered_in_the_admin_timezone_and_stored_in_utc(): void
    {
        $photo = $this->draft();

        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->put(route('admin.astrophotos.update', $photo), ['title' => $photo->title, 'captured_at' => '2026-10-04', 'is_published' => 0, 'publish_at' => '2026-11-07T20:00'])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-11-07 19:00:00', $photo->fresh()->publish_at->format('Y-m-d H:i:s'));
        $this->get(route('admin.astrophotos.index'))->assertSee('Programmée · 7 nov. 20:00');
    }

    public function test_a_past_publication_time_is_rejected_and_the_input_is_kept(): void
    {
        $photo = $this->draft();

        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->from(route('admin.astrophotos.edit', $photo))
            ->put(route('admin.astrophotos.update', $photo), ['title' => $photo->title, 'captured_at' => '2026-10-04', 'is_published' => 0, 'publish_at' => '2026-11-02T09:00'])
            ->assertSessionHasErrors('publish_at')
            ->assertSessionHasInput('publish_at', '2026-11-02T09:00');
    }

    public function test_due_drafts_are_published_with_the_newsletter_only_once_images_are_ready(): void
    {
        NewsletterSubscriber::factory()->create();
        $due = $this->draft();
        $notReady = $this->draft(ready: false);
        $later = $this->draft();
        $due->update(['publish_at' => now()->subMinute()]);
        $notReady->update(['publish_at' => now()->subMinute()]);
        $later->update(['publish_at' => now()->addDay()]);

        $this->artisan('astrophotos:publish-scheduled')
            ->expectsOutputToContain('1 photo(s) publiée(s), 1 en attente des conversions')
            ->assertSuccessful();

        $this->assertTrue($due->fresh()->is_published);
        $this->assertNull($due->fresh()->publish_at);
        $this->assertFalse($notReady->fresh()->is_published);
        $this->assertFalse($later->fresh()->is_published);
        Mail::assertQueued(NewAstrophotoPublished::class, 1);
    }

    public function test_publishing_or_unpublishing_by_hand_cancels_the_schedule(): void
    {
        $photo = $this->draft();
        $photo->update(['publish_at' => now()->addDay()]);

        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']))
            ->patch(route('admin.astrophotos.publication', $photo), ['is_published' => 1])
            ->assertSessionHasNoErrors();

        $this->assertNull($photo->fresh()->publish_at);
    }
}
