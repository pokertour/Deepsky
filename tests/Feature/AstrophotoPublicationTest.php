<?php

namespace Tests\Feature;

use App\Enums\ConversionStatus;
use App\Jobs\PerformAstrophotoConversionsJob;
use App\Mail\NewAstrophotoPublished;
use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\Conversions\ConversionCollection;
use Tests\TestCase;

class AstrophotoPublicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com'], 'media-library.queue_conversions_after_database_commit' => false]);
        Storage::fake('s3');
        Queue::fake();
        Mail::fake();
    }

    public function test_new_upload_is_a_draft_and_json_upload_returns_redirect_without_sending_mail(): void
    {
        NewsletterSubscriber::factory()->create();
        $this->actingAs($this->admin())->postJson(route('admin.astrophotos.store'), [
            'title' => 'Brouillon Rosette',
            'captured_at' => '2026-10-04',
            'image' => UploadedFile::fake()->image('rosette.jpg', 100, 100),
        ])->assertOk()->assertJsonPath('redirect', route('admin.astrophotos.index'));

        $photo = Astrophoto::query()->firstOrFail();
        $this->assertFalse($photo->is_published);
        $this->assertSame(ConversionStatus::Queued, $photo->conversion_status);
        Mail::assertNothingQueued();
        Queue::assertPushed(PerformAstrophotoConversionsJob::class);
        $this->get(route('welcome'))->assertDontSee('Brouillon Rosette');
    }

    public function test_only_ready_drafts_can_be_published_and_newsletter_is_sent_once(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();
        $photo = $this->draftWithMedia();
        $payload = ['title' => $photo->title, 'captured_at' => '2026-10-04', 'is_published' => 1];

        $this->actingAs($this->admin())->put(route('admin.astrophotos.update', $photo), $payload)
            ->assertSessionHasErrors('is_published');
        $this->assertFalse($photo->fresh()->is_published);

        $media = $photo->getFirstMedia('astros');
        $media->generated_conversions = ['thumb' => true, 'optimized' => true, 'full_res' => true];
        $media->save();
        $this->put(route('admin.astrophotos.update', $photo), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($photo->fresh()->is_published);
        Mail::assertQueued(NewAstrophotoPublished::class, fn ($mail) => $mail->hasTo($subscriber->email));
        $this->put(route('admin.astrophotos.update', $photo), [...$payload, 'is_published' => 0])->assertSessionHasNoErrors();
        $this->put(route('admin.astrophotos.update', $photo), $payload)->assertSessionHasNoErrors();
        Mail::assertQueuedCount(1);
    }

    public function test_failed_conversions_are_visible_and_can_be_retried(): void
    {
        $photo = $this->draftWithMedia();
        $media = $photo->getFirstMedia('astros');
        $job = new PerformAstrophotoConversionsJob(ConversionCollection::createForMedia($media), $media);
        $job->failed(new \RuntimeException('Conversion failed'));
        $this->assertSame(ConversionStatus::Failed, $photo->fresh()->conversion_status);

        $this->actingAs($this->admin())->post(route('admin.astrophotos.retry-conversions', $photo))
            ->assertSessionHas('success');
        $this->assertSame(ConversionStatus::Queued, $photo->fresh()->conversion_status);
        Queue::assertPushed(PerformAstrophotoConversionsJob::class, 2);
    }

    public function test_upload_does_not_generate_thumbnail_synchronously(): void
    {
        $this->actingAs($this->admin())->postJson(route('admin.astrophotos.store'), [
            'title' => 'Upload léger',
            'captured_at' => '2026-10-04',
            'image' => UploadedFile::fake()->image('m31.jpg', 100, 100),
        ])->assertOk();

        $photo = Astrophoto::query()->firstOrFail();
        $this->assertFalse($photo->getFirstMedia('astros')->hasGeneratedConversion('thumb'));
        $this->assertNull($photo->thumbnailUrl());
        Queue::assertPushedOn('media', PerformAstrophotoConversionsJob::class);

        $this->get(route('admin.astrophotos.index'))
            ->assertOk()
            ->assertSee('CONVERSION…')
            ->assertDontSee('conversions/m31-thumb', false);
        $this->get(route('admin.astrophotos.edit', $photo))
            ->assertOk()
            ->assertSee('Miniature en cours de conversion');
    }

    public function test_thumbnail_url_is_available_once_generated(): void
    {
        $photo = $this->draftWithMedia();
        $media = $photo->getFirstMedia('astros');
        $media->generated_conversions = ['thumb' => true];
        $media->save();

        $this->assertSame($media->getUrl('thumb'), $photo->fresh()->thumbnailUrl());
        $this->assertNull($photo->fresh()->thumbnailUrl('starless'));
    }

    public function test_conversions_run_on_dedicated_queue_with_timeout_below_retry_after(): void
    {
        $this->draftWithMedia();

        Queue::assertPushedOn('media', PerformAstrophotoConversionsJob::class);

        $media = Astrophoto::query()->firstOrFail()->getFirstMedia('astros');
        $job = new PerformAstrophotoConversionsJob(ConversionCollection::createForMedia($media), $media);

        $this->assertTrue($job->failOnTimeout);
        foreach (['database', 'redis'] as $connection) {
            $this->assertLessThan(config("queue.connections.{$connection}.retry_after"), $job->timeout);
        }
    }

    public function test_draft_preview_and_publication_filter_are_admin_only(): void
    {
        $photo = $this->draftWithMedia();
        $this->get(route('astrophoto.full', $photo))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route('astrophoto.full', $photo))->assertNotFound();
        $this->actingAs($this->admin())->get(route('astrophoto.full', $photo))->assertOk();
        $published = Astrophoto::factory()->create(['title' => 'Published other photo']);
        $this->get(route('admin.astrophotos.index', ['status' => 'draft']))
            ->assertOk()->assertSee($photo->title)->assertDontSee($published->title);
    }

    public function test_processing_status_is_private_and_stuck_worker_can_be_retried(): void
    {
        $photo = $this->draftWithMedia();
        $media = $photo->getFirstMedia('astros');
        $media->setCustomProperty('processing_status', ConversionStatus::Converting->value);
        $media->setCustomProperty('processing_started_at', now()->subMinutes(31)->toIso8601String());
        $media->save();
        $this->getJson(route('admin.astrophotos.processing-status', $photo))->assertUnauthorized();
        $this->actingAs($this->admin())->getJson(route('admin.astrophotos.processing-status', $photo))
            ->assertOk()->assertJsonPath('conversion_status', ConversionStatus::Failed->value)->assertHeader('Cache-Control', 'no-store, private');
        $this->post(route('admin.astrophotos.retry-conversions', $photo))->assertSessionHas('success');
        $this->assertSame(ConversionStatus::Queued, $photo->fresh()->conversion_status);
    }

    public function test_quick_publication_preserves_photo_and_list_filters_and_notifies_once(): void
    {
        NewsletterSubscriber::factory()->create();
        $photo = $this->draftWithMedia();
        $media = $photo->getFirstMedia('astros');
        $media->generated_conversions = ['thumb' => true, 'optimized' => true, 'full_res' => true];
        $media->save();
        $title = $photo->title;
        $exposure = $photo->exposure;
        $listUrl = route('admin.astrophotos.index', ['status' => 'draft', 'search' => $title, 'page' => 2]);

        $this->actingAs($this->admin())->from($listUrl)
            ->patch(route('admin.astrophotos.publication', $photo), ['is_published' => 1, 'title' => 'Unwanted change'])
            ->assertSessionHasNoErrors()->assertRedirect($listUrl)->assertSessionHas('success');
        $this->assertTrue($photo->fresh()->is_published);
        $this->assertSame($title, $photo->fresh()->title);
        $this->assertSame($exposure, $photo->fresh()->exposure);
        Mail::assertQueuedCount(1);

        $this->patch(route('admin.astrophotos.publication', $photo), ['is_published' => 1])->assertSessionHasNoErrors();
        $this->patch(route('admin.astrophotos.publication', $photo), ['is_published' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($photo->fresh()->is_published);
        $this->patch(route('admin.astrophotos.publication', $photo), ['is_published' => 1])->assertSessionHasNoErrors();
        Mail::assertQueuedCount(1);
    }

    public function test_quick_publication_rejects_missing_queued_failed_and_incomplete_starless_images(): void
    {
        $this->actingAs($this->admin());
        $missing = Astrophoto::factory()->create(['is_published' => false]);
        $queued = $this->draftWithMedia();
        $failed = $this->draftWithMedia();
        $failed->getFirstMedia('astros')->setCustomProperty('processing_status', ConversionStatus::Failed->value)->save();
        $starless = $this->draftWithMedia();
        $media = $starless->getFirstMedia('astros');
        $media->generated_conversions = ['thumb' => true, 'optimized' => true, 'full_res' => true];
        $media->save();
        $starless->addMedia(UploadedFile::fake()->image('starless.jpg', 100, 100))->toMediaCollection('starless', 's3');

        foreach ([$missing, $queued, $failed, $starless] as $photo) {
            $this->patch(route('admin.astrophotos.publication', $photo), ['is_published' => 1])
                ->assertSessionHasErrors('is_published');
            $this->assertFalse($photo->fresh()->is_published);
        }
        Mail::assertNothingQueued();
    }

    public function test_quick_publication_requires_admin_and_explicit_valid_state(): void
    {
        $photo = $this->draftWithMedia();
        $url = route('admin.astrophotos.publication', $photo);
        $this->patchJson($url, ['is_published' => 1])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->patchJson($url, ['is_published' => 1])->assertForbidden();
        $this->actingAs($this->admin())->patch($url, [])->assertSessionHasErrors('is_published');
        $this->patch($url, ['is_published' => 'invalid'])->assertSessionHasErrors('is_published');
        $this->assertFalse($photo->fresh()->is_published);
    }

    public function test_photo_list_offers_quick_publication_preview_and_return_to_draft(): void
    {
        $photo = $this->draftWithMedia();
        $this->actingAs($this->admin())->withoutVite()->get(route('admin.astrophotos.index'))
            ->assertOk()->assertSee(route('admin.astrophotos.publication', $photo), false)
            ->assertSee('Publier')->assertSee('Aperçu')->assertSee('Publication disponible lorsque les images seront prêtes.');

        $media = $photo->getFirstMedia('astros');
        $media->generated_conversions = ['thumb' => true, 'optimized' => true, 'full_res' => true];
        $media->save();
        $this->get(route('admin.astrophotos.index'))->assertOk()
            ->assertSee('Publier')->assertDontSee('Publication disponible lorsque les images seront prêtes.');
        $photo->update(['is_published' => true]);
        $this->get(route('admin.astrophotos.index'))->assertOk()
            ->assertSee('Passer en brouillon')->assertSee('Voir')->assertDontSee('>Publier</button>', false);
    }

    private function draftWithMedia(): Astrophoto
    {
        $photo = Astrophoto::factory()->create(['is_published' => false]);
        $photo->addMedia(UploadedFile::fake()->image('rosette.jpg', 100, 100))
            ->withCustomProperties(['processing_status' => ConversionStatus::Queued->value])->toMediaCollection('astros', 's3');

        return $photo->fresh();
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }
}
