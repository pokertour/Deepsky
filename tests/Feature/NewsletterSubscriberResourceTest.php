<?php

namespace Tests\Feature;

use App\Mail\ConfirmNewsletterSubscription;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterSubscriberResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com']]);
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']));
    }

    public function test_list_can_be_searched_by_email(): void
    {
        NewsletterSubscriber::factory()->create(['email' => 'target@example.com']);
        NewsletterSubscriber::factory()->create(['email' => 'other@example.com']);

        $this->get(route('admin.newsletter.index', ['search' => 'target@example.com']))
            ->assertOk()->assertSee('target@example.com')->assertDontSee('other@example.com');
    }

    public function test_subscriber_can_be_deleted(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();
        $this->delete(route('admin.newsletter.destroy', $subscriber))->assertSessionHas('success');
        $this->assertModelMissing($subscriber);
    }

    public function test_confirmation_can_be_resent_and_missing_token_is_regenerated(): void
    {
        Mail::fake();
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create(['confirmation_token' => null]);

        $this->post(route('admin.newsletter.resend', $subscriber))->assertSessionHas('success');

        $this->assertNotNull($subscriber->refresh()->confirmation_token);
        Mail::assertQueued(ConfirmNewsletterSubscription::class);
    }

    public function test_confirmation_cannot_be_resent_to_confirmed_subscriber(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();
        $this->post(route('admin.newsletter.resend', $subscriber))->assertUnprocessable();
    }

    public function test_admin_resend_renews_validity_and_shares_the_public_cooldown(): void
    {
        Mail::fake();
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create(['created_at' => now()->subDays(10)]);

        $this->post(route('admin.newsletter.resend', $subscriber))->assertSessionHas('success');
        $this->artisan('newsletter:purge-unconfirmed')->assertSuccessful();
        $this->assertModelExists($subscriber);
        $this->assertTrue($subscriber->refresh()->confirmation_sent_at->isToday());

        $this->post(route('admin.newsletter.resend', $subscriber))->assertSessionHas('success');
        $this->post(route('newsletter.subscribe'), ['email' => $subscriber->email])->assertSessionHas('newsletter_status');
        Mail::assertQueuedCount(1);
    }
}
