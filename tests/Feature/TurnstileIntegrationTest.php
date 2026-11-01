<?php

namespace Tests\Feature;

use App\Enums\CaptchaProvider;
use App\Mail\ConfirmNewsletterSubscription;
use App\Services\CaptchaService;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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
}
