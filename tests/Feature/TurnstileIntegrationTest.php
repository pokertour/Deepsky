<?php

namespace Tests\Feature;

use App\Enums\CaptchaProvider;
use App\Mail\ConfirmNewsletterSubscription;
use App\Models\User;
use App\Services\CaptchaService;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TurnstileIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private const string SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        $settings = app(IntegrationSettings::class);
        $settings->captcha_provider = CaptchaProvider::Turnstile;
        $settings->captcha_site_key = '0x4AAAAAADv9qJVhfdrsD051';
        $settings->captcha_secret_key = 'test-secret-not-a-real-key';
        $settings->save();
        config(['turnstile.hostnames' => ['deepsky.nexus']]);
        Http::preventStrayRequests();
        Mail::fake();
    }

    public function test_the_existing_widget_is_rendered_with_the_newsletter_action_without_exposing_the_secret(): void
    {
        $this->get(route('welcome'))->assertOk()
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertSee('data-sitekey="0x4AAAAAADv9qJVhfdrsD051"', false)
            ->assertSee('data-action="newsletter"', false)
            ->assertDontSee('test-secret-not-a-real-key', false);
    }

    public function test_the_protected_handler_accepts_a_valid_token_and_rejects_a_duplicate_response(): void
    {
        Http::fake([self::SITEVERIFY => Http::sequence()
            ->push(['success' => true, 'action' => 'newsletter', 'hostname' => 'deepsky.nexus'])
            ->push(['success' => false, 'error-codes' => ['timeout-or-duplicate']]),
        ]);
        $payload = ['email' => 'astro@example.com', 'cf-turnstile-response' => 'single-use-token'];

        $this->post(route('newsletter.subscribe'), $payload)->assertRedirect()->assertSessionHas('newsletter_status');
        $this->post(route('newsletter.subscribe'), $payload)->assertRedirect()->assertSessionHasErrors('email')
            ->assertSessionMissing('_old_input.cf-turnstile-response');

        $this->assertDatabaseCount('newsletter_subscribers', 1);
        Mail::assertQueued(ConfirmNewsletterSubscription::class, 1);
        Http::assertSent(fn ($request): bool => $request->url() === self::SITEVERIFY
            && $request['response'] === 'single-use-token'
            && $request['secret'] === 'test-secret-not-a-real-key'
            && $request['remoteip'] === '127.0.0.1'
            && str_contains($request->header('Content-Type')[0], 'application/x-www-form-urlencoded'));
    }

    #[DataProvider('untrustedResponses')]
    public function test_success_alone_does_not_allow_an_untrusted_action_or_hostname(array $response): void
    {
        Http::fake([self::SITEVERIFY => Http::response($response)]);
        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com', 'cf-turnstile-response' => 'token',
        ])->assertRedirect()->assertSessionHasErrors('email');
        $this->assertDatabaseCount('newsletter_subscribers', 0);
        Mail::assertNothingQueued();
    }

    public static function untrustedResponses(): array
    {
        return [
            'missing metadata' => [['success' => true]],
            'wrong action' => [['success' => true, 'action' => 'login', 'hostname' => 'deepsky.nexus']],
            'wrong hostname' => [['success' => true, 'action' => 'newsletter', 'hostname' => 'attacker.example']],
            'unapproved subdomain' => [['success' => true, 'action' => 'newsletter', 'hostname' => 'www.deepsky.nexus']],
            'non-boolean success' => [['success' => 'true', 'action' => 'newsletter', 'hostname' => 'deepsky.nexus']],
        ];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_token_inputs_are_rejected_without_contacting_cloudflare(mixed $token): void
    {
        Http::fake();
        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com', 'cf-turnstile-response' => $token,
        ])->assertRedirect()->assertSessionHasErrors('email');
        Http::assertNothingSent();
        $this->assertDatabaseCount('newsletter_subscribers', 0);
        Mail::assertNothingQueued();
    }

    public static function invalidTokens(): array
    {
        return [
            'missing' => [null], 'empty' => [''], 'whitespace' => ['   '],
            'array' => [['token']], 'number' => [123], 'oversized' => [str_repeat('x', 2049)],
        ];
    }

    #[DataProvider('missingConfiguration')]
    public function test_enabled_but_incomplete_configuration_fails_closed(string $key, mixed $value): void
    {
        if ($key === 'turnstile.hostnames') {
            config([$key => $value]);
        } else {
            $settings = app(IntegrationSettings::class);
            $settings->{$key} = $value;
            $settings->save();
        }
        Http::fake();
        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com', 'cf-turnstile-response' => 'token',
        ])->assertRedirect()->assertSessionHasErrors('email');
        Http::assertNothingSent();
        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public static function missingConfiguration(): array
    {
        return [
            'secret' => ['captcha_secret_key', null],
            'site key' => ['captcha_site_key', null],
            'hostname allowlist' => ['turnstile.hostnames', []],
        ];
    }

    public function test_connection_failures_reject_the_submission_without_side_effects(): void
    {
        Http::fake([self::SITEVERIFY => Http::failedConnection()]);
        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com', 'cf-turnstile-response' => 'token',
        ])->assertRedirect()->assertSessionHasErrors('email');
        $this->assertDatabaseCount('newsletter_subscribers', 0);
        Mail::assertNothingQueued();
    }

    public function test_admin_settings_remain_authoritative_even_with_obsolete_environment_configuration(): void
    {
        config([
            'turnstile.enabled' => true,
            'turnstile.site_key' => 'obsolete-site-key',
            'turnstile.secret' => 'obsolete-secret',
        ]);
        $this->get(route('welcome'))->assertOk()
            ->assertSee('data-sitekey="0x4AAAAAADv9qJVhfdrsD051"', false)
            ->assertDontSee('obsolete-site-key', false);

        Http::fake([self::SITEVERIFY => Http::response([
            'success' => true, 'action' => 'newsletter', 'hostname' => 'deepsky.nexus',
        ])]);
        $this->post(route('newsletter.subscribe'), [
            'email' => 'astro@example.com', 'cf-turnstile-response' => 'token',
        ])->assertSessionHas('newsletter_status');
        Http::assertSent(fn ($request): bool => $request['secret'] === 'test-secret-not-a-real-key');

        $settings = app(IntegrationSettings::class);
        $settings->captcha_provider = CaptchaProvider::None;
        $settings->save();
        $this->assertFalse(app(CaptchaService::class)->isEnabled());
    }

    public function test_production_rejects_loopback_hosts_even_if_they_are_in_the_allowlist(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['turnstile.hostnames' => ['localhost', '127.0.0.1']]);
        Http::fake();
        $this->assertFalse(app(CaptchaService::class)->verify('token', null, 'newsletter'));
        Http::assertNothingSent();
    }

    public function test_admin_checks_that_the_stored_secret_is_accepted_by_siteverify(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $admin = User::factory()->create(['email' => 'owner@example.com']);
        Http::fake([self::SITEVERIFY => Http::sequence()
            ->push(['success' => false, 'error-codes' => ['invalid-input-response']])
            ->push(['success' => false, 'error-codes' => ['invalid-input-secret']]),
        ]);

        $this->actingAs($admin)->from(route('admin.settings.edit'))
            ->post(route('admin.settings.captcha.test'))
            ->assertRedirect(route('admin.settings.edit'))
            ->assertSessionHas('success', 'Clé secrète acceptée par Cloudflare Turnstile : Siteverify fonctionne.');

        $this->from(route('admin.settings.edit'))
            ->post(route('admin.settings.captcha.test'))
            ->assertSessionHas('error', 'Clé secrète refusée par Cloudflare Turnstile : vérifie qu’elle correspond bien au widget.');

        Http::assertSent(fn ($request): bool => $request->url() === self::SITEVERIFY
            && $request['secret'] === 'test-secret-not-a-real-key'
            && $request['response'] === 'deepsky-secret-check');

        $this->get(route('admin.settings.edit'))->assertSee('Une clé secrète est enregistrée.')->assertSee('Tester la clé secrète');
    }

    public function test_rejections_are_logged_with_their_reason_but_never_the_token_or_secret(): void
    {
        Log::spy();
        Http::fake([self::SITEVERIFY => Http::response(['success' => false, 'error-codes' => ['timeout-or-duplicate']])]);

        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com', 'cf-turnstile-response' => 'expired-token']);
        $this->post(route('newsletter.subscribe'), ['email' => 'astro@example.com']);

        Log::shouldHaveReceived('warning')
            ->with('Captcha newsletter refusé : jeton refusé par Cloudflare Turnstile', ['provider' => 'turnstile', 'error_codes' => ['timeout-or-duplicate']]);
        Log::shouldHaveReceived('warning')
            ->with('Captcha newsletter refusé : aucun jeton reçu avec le formulaire', ['provider' => 'turnstile', 'error_codes' => []]);
    }
}
