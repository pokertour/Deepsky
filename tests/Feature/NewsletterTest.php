<?php

namespace Tests\Feature;

use App\Enums\CaptchaProvider;
use App\Mail\ConfirmNewsletterSubscription;
use App\Mail\NewAstrophotoPublished;
use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    private function enableCaptcha(CaptchaProvider $provider): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->captcha_provider = $provider;
        $settings->captcha_site_key = 'site-key';
        $settings->captcha_secret_key = 'secret-key';
        $settings->save();
    }

    public function test_visitor_subscription_creates_unconfirmed_subscriber_and_sends_confirmation_email(): void
    {
        Mail::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com'])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'astro@example.com',
            'confirmed_at' => null,
        ]);

        Mail::assertQueued(
            ConfirmNewsletterSubscription::class,
            fn (ConfirmNewsletterSubscription $mail): bool => $mail->hasTo('astro@example.com'),
        );
    }

    public function test_subscriber_can_confirm_subscription_with_token(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create();

        $this->get(route('newsletter.confirm', $subscriber->confirmation_token))
            ->assertOk()
            ->assertSee('Inscription confirmée');

        $subscriber->refresh();
        $this->assertTrue($subscriber->isConfirmed());
        $this->assertNull($subscriber->confirmation_token);
    }

    public function test_confirmation_with_unknown_token_confirms_nobody(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create();

        $this->get(route('newsletter.confirm', 'unknown-token'))
            ->assertOk()
            ->assertSee('plus valide');

        $this->assertFalse($subscriber->refresh()->isConfirmed());
    }

    public function test_resubscribing_while_unconfirmed_respects_the_confirmation_cooldown(): void
    {
        Mail::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com']);
        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com'])
            ->assertSessionHas('newsletter_status', fn ($message): bool => str_contains($message, 'déjà été envoyé'));

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        Mail::assertQueuedCount(1);

        $this->travel(5)->minutes();
        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com']);
        Mail::assertQueuedCount(2);
    }

    public function test_resending_confirmation_renews_the_purge_deadline(): void
    {
        Mail::fake();
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create([
            'created_at' => now()->subDays(10),
            'confirmation_sent_at' => now()->subDays(10),
        ]);

        $this->post(route('newsletter.subscribe'), ['email' => $subscriber->email])->assertSessionHas('newsletter_status');
        $this->assertTrue($subscriber->refresh()->confirmation_sent_at->isToday());
        $this->artisan('newsletter:purge-unconfirmed')->assertSuccessful();
        $this->assertModelExists($subscriber);

        $this->get(route('newsletter.confirm', $subscriber->confirmation_token))->assertSee('Inscription confirmée');
        $this->assertTrue($subscriber->refresh()->isConfirmed());
    }

    public function test_expired_confirmation_is_rejected_even_before_the_daily_purge(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create([
            'confirmation_sent_at' => now()->subDays(7),
        ]);

        $this->get(route('newsletter.confirm', $subscriber->confirmation_token))->assertSee('plus valide');
        $this->assertFalse($subscriber->refresh()->isConfirmed());
        $this->artisan('newsletter:purge-unconfirmed')->assertSuccessful();
        $this->assertModelMissing($subscriber);
    }

    public function test_legacy_confirmation_uses_the_original_subscription_date(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create(['created_at' => now()->subDays(8)]);
        $this->get(route('newsletter.confirm', $subscriber->confirmation_token))->assertSee('plus valide');
        $this->assertFalse($subscriber->refresh()->isConfirmed());
    }

    public function test_subscribing_with_an_already_confirmed_email_does_not_resend_confirmation(): void
    {
        Mail::fake();

        $subscriber = NewsletterSubscriber::factory()->create();

        $this->post(route('newsletter.subscribe'), ['email' => $subscriber->email])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status');

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        Mail::assertNothingQueued();
    }

    public function test_honeypot_submission_pretends_to_succeed_without_creating_a_subscriber(): void
    {
        Mail::fake();

        $this->post(route('newsletter.subscribe'), [
            'email' => 'bot@example.com',
            'website' => 'https://spam.example.com',
        ])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
        Mail::assertNothingQueued();
    }

    public function test_email_is_normalized_and_subscribing_twice_does_not_duplicate(): void
    {
        Mail::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'Astro@Example.com']);
        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com']);

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'astro@example.com']);
    }

    public function test_subscription_requires_a_valid_email(): void
    {
        $this->from(route('welcome'))
            ->post(route('newsletter.subscribe'), ['email' => 'not-an-email'])
            ->assertRedirect(route('welcome'))
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_subscription_succeeds_when_turnstile_token_is_valid(): void
    {
        $this->enableCaptcha(CaptchaProvider::Turnstile);
        Http::fake([CaptchaProvider::Turnstile->verifyUrl() => Http::response([
            'success' => true, 'action' => 'newsletter', 'hostname' => 'deepsky.nexus',
        ])]);

        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect()->assertSessionHas('newsletter_status');

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'astro@example.com']);

        Http::assertSent(fn ($request): bool => $request->url() === CaptchaProvider::Turnstile->verifyUrl()
            && $request['secret'] === 'secret-key'
            && $request['response'] === 'valid-token');
    }

    public function test_subscription_fails_when_turnstile_token_is_rejected(): void
    {
        $this->enableCaptcha(CaptchaProvider::Turnstile);
        Http::fake([CaptchaProvider::Turnstile->verifyUrl() => Http::response(['success' => false])]);

        $this->from(route('welcome'))
            ->post(route('newsletter.subscribe'), [
                'email' => 'astro@example.com',
                'cf-turnstile-response' => 'bad-token',
            ])
            ->assertRedirect(route('welcome'))
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_subscription_fails_when_captcha_token_is_missing(): void
    {
        $this->enableCaptcha(CaptchaProvider::Hcaptcha);
        Http::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
        Http::assertNothingSent();
    }

    public function test_subscription_verifies_hcaptcha_token(): void
    {
        $this->enableCaptcha(CaptchaProvider::Hcaptcha);
        Http::fake([CaptchaProvider::Hcaptcha->verifyUrl() => Http::response(['success' => true])]);

        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com',
            'h-captcha-response' => 'valid-token',
        ])->assertRedirect()->assertSessionHas('newsletter_status');

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'astro@example.com']);
    }

    public function test_subscription_fails_when_captcha_verification_request_errors(): void
    {
        $this->enableCaptcha(CaptchaProvider::Turnstile);
        Http::fake([CaptchaProvider::Turnstile->verifyUrl() => Http::response(null, 500)]);

        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com',
            'cf-turnstile-response' => 'token',
        ])->assertRedirect()->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_subscription_skips_captcha_when_provider_is_none(): void
    {
        Http::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com'])
            ->assertRedirect()
            ->assertSessionHas('newsletter_status');

        $this->assertDatabaseHas('newsletter_subscribers', ['email' => 'astro@example.com']);
        Http::assertNothingSent();
    }

    public function test_subscription_rejects_when_the_selected_captcha_is_missing_keys(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->captcha_provider = CaptchaProvider::Turnstile;
        $settings->captcha_site_key = null;
        $settings->captcha_secret_key = null;
        $settings->save();

        Http::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com'])
            ->assertRedirect()
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
        Http::assertNothingSent();
    }

    public function test_unsubscribe_link_asks_for_confirmation_without_deleting(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))
            ->assertOk()
            ->assertSee('Me désinscrire')
            ->assertSee(route('newsletter.unsubscribe.store', $subscriber->unsubscribe_token), false);

        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $subscriber->id]);
    }

    public function test_subscriber_can_confirm_unsubscription(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->post(route('newsletter.unsubscribe.store', $subscriber->unsubscribe_token))
            ->assertOk()
            ->assertSee('désinscrit');

        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $subscriber->id]);
    }

    public function test_one_click_unsubscribe_via_post_works_without_csrf_token(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();

        $this->post(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))
            ->assertOk();

        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $subscriber->id]);
    }

    public function test_unsubscribe_with_unknown_token_does_not_delete_anyone(): void
    {
        NewsletterSubscriber::factory()->create();

        $this->get(route('newsletter.unsubscribe', 'unknown-token'))
            ->assertOk();
        $this->post(route('newsletter.unsubscribe.store', 'unknown-token'))
            ->assertOk();

        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_newsletter_email_has_list_unsubscribe_headers(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();
        $photo = Astrophoto::factory()->create();

        $headers = (new NewAstrophotoPublished($photo, $subscriber))->headers();

        $this->assertSame(
            '<'.route('newsletter.unsubscribe', $subscriber->unsubscribe_token).'>',
            $headers->text['List-Unsubscribe'],
        );
        $this->assertSame('List-Unsubscribe=One-Click', $headers->text['List-Unsubscribe-Post']);
    }

    public function test_purge_command_deletes_only_old_unconfirmed_subscribers(): void
    {
        $oldUnconfirmed = NewsletterSubscriber::factory()->unconfirmed()->create(['created_at' => now()->subDays(8)]);
        $recentUnconfirmed = NewsletterSubscriber::factory()->unconfirmed()->create(['created_at' => now()->subDays(2)]);
        $oldConfirmed = NewsletterSubscriber::factory()->create(['created_at' => now()->subDays(30)]);

        $this->artisan('newsletter:purge-unconfirmed')->assertSuccessful();

        $this->assertDatabaseMissing('newsletter_subscribers', ['id' => $oldUnconfirmed->id]);
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $recentUnconfirmed->id]);
        $this->assertDatabaseHas('newsletter_subscribers', ['id' => $oldConfirmed->id]);
    }

    public function test_subscribers_receive_an_email_when_a_photo_is_published(): void
    {
        Mail::fake();

        $subscribers = NewsletterSubscriber::factory()->count(2)->create();

        $photo = Astrophoto::factory()->create();

        foreach ($subscribers as $subscriber) {
            Mail::assertQueued(
                NewAstrophotoPublished::class,
                fn (NewAstrophotoPublished $mail): bool => $mail->hasTo($subscriber->email)
                    && $mail->astrophoto->is($photo)
            );
        }

        Mail::assertQueuedCount(2);
    }

    public function test_unconfirmed_subscribers_do_not_receive_the_newsletter(): void
    {
        Mail::fake();

        NewsletterSubscriber::factory()->unconfirmed()->create();
        $confirmed = NewsletterSubscriber::factory()->create();

        Astrophoto::factory()->create();

        Mail::assertQueued(
            NewAstrophotoPublished::class,
            fn (NewAstrophotoPublished $mail): bool => $mail->hasTo($confirmed->email),
        );
        Mail::assertQueuedCount(1);
    }

    public function test_confirmation_email_contains_confirmation_link(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create();

        $mailable = new ConfirmNewsletterSubscription($subscriber);

        $mailable->assertSeeInHtml(route('newsletter.confirm', $subscriber->confirmation_token));
    }

    public function test_no_email_is_queued_when_there_are_no_subscribers(): void
    {
        Mail::fake();

        Astrophoto::factory()->create();

        Mail::assertNothingQueued();
    }

    public function test_newsletter_email_contains_unsubscribe_link(): void
    {
        $subscriber = NewsletterSubscriber::factory()->create();
        $photo = Astrophoto::factory()->create(['title' => 'NGC 7000']);

        $mailable = new NewAstrophotoPublished($photo, $subscriber);

        $mailable->assertSeeInHtml(route('newsletter.unsubscribe', $subscriber->unsubscribe_token));
        $mailable->assertHasSubject('Nouvelle astrophoto : NGC 7000');
    }
}
