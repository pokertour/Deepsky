<?php

namespace Tests\Feature;

use App\Enums\CaptchaProvider;
use App\Models\User;
use App\Settings\IntegrationSettings;
use App\Settings\MailSettings;
use App\Settings\NotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com']]);
        IntegrationSettings::fake([
            'astrometry_api_key' => null,
            'astrobin_api_key' => null,
            'astrobin_api_secret' => null,
            'captcha_provider' => CaptchaProvider::None,
            'captcha_site_key' => null,
            'captcha_secret_key' => null,
        ]);
        MailSettings::fake([
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => null,
            'password' => null,
            'scheme' => null,
            'from_address' => 'contact@deepsky.test',
            'from_name' => 'Deepsky',
        ]);
        NotificationSettings::fake([
            'telegram_enabled' => false,
            'telegram_bot_token' => null,
            'telegram_chat_id' => null,
            'gotify_enabled' => false,
            'gotify_url' => null,
            'gotify_token' => null,
            'ntfy_enabled' => false,
            'ntfy_url' => 'https://ntfy.sh',
            'ntfy_topic' => null,
            'mail_enabled' => false,
            'mail_recipient' => null,
            'alert_daily_conditions' => false,
            'alert_best_targets' => false,
            'alert_weekly_report' => false,
            'alert_task_failures' => true,
            'daily_min_score' => 65,
            'daily_time' => '16:00',
            'weekly_time' => '09:00',
        ]);
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']));
    }

    public function test_page_renders_with_current_settings(): void
    {
        MailSettings::fake(['host' => 'smtp.example.com', 'port' => 587, 'username' => null, 'password' => null, 'scheme' => null, 'from_address' => 'contact@deepsky.test', 'from_name' => 'Deepsky']);
        IntegrationSettings::fake(['astrometry_api_key' => 'astro-key', 'astrobin_api_key' => null, 'astrobin_api_secret' => null, 'captcha_provider' => CaptchaProvider::None, 'captcha_site_key' => null, 'captcha_secret_key' => null]);

        $this->get(route('admin.settings.edit'))->assertOk()->assertSee('smtp.example.com')->assertSee('Une clé est actuellement configurée.');
    }

    public function test_settings_can_be_saved(): void
    {
        $this->put(route('admin.settings.update'), $this->validData([
            'astrometry_api_key' => 'new-astro-key',
            'host' => 'smtp.new-host.com',
            'port' => 465,
            'password' => 'new-password',
        ]))->assertSessionHas('success');

        $this->assertSame('new-astro-key', app(IntegrationSettings::class)->astrometry_api_key);
        $this->assertSame('smtp.new-host.com', app(MailSettings::class)->host);
        $this->assertSame('new-password', app(MailSettings::class)->password);
    }

    public function test_captcha_keys_are_required_when_enabled(): void
    {
        $this->from(route('admin.settings.edit'))->put(route('admin.settings.update'), $this->validData([
            'captcha_provider' => CaptchaProvider::Hcaptcha->value,
            'captcha_site_key' => null,
            'captcha_secret_key' => null,
        ]))->assertSessionHasErrors(['captcha_site_key', 'captcha_secret_key']);
    }

    public function test_gemini_key_and_model_can_be_saved_and_blank_key_is_preserved(): void
    {
        $this->put(route('admin.settings.update'), $this->validData([
            'gemini_api_key' => 'new-gemini-key', 'gemini_model' => 'gemini-2.5-flash-lite',
        ]))->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('new-gemini-key', app(IntegrationSettings::class)->gemini_api_key);
        $this->put(route('admin.settings.update'), $this->validData([
            'gemini_api_key' => '', 'gemini_model' => 'gemini-2.5-flash',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('new-gemini-key', app(IntegrationSettings::class)->gemini_api_key);
        $this->assertSame('gemini-2.5-flash', app(IntegrationSettings::class)->gemini_model);
    }

    public function test_smtp_fields_are_validated(): void
    {
        $this->put(route('admin.settings.update'), $this->validData(['host' => null, 'from_address' => null]))
            ->assertSessionHasErrors(['host', 'from_address']);
    }

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'astrometry_api_key' => null,
            'astrobin_api_key' => null,
            'astrobin_api_secret' => null,
            'captcha_provider' => CaptchaProvider::None->value,
            'captcha_site_key' => null,
            'captcha_secret_key' => null,
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => null,
            'password' => null,
            'scheme' => null,
            'from_address' => 'contact@deepsky.test',
            'from_name' => 'Deepsky',
        ], $overrides);
    }
}
