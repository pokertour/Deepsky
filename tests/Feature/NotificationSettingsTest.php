<?php

namespace Tests\Feature;

use App\Data\Notifications\NightScopeDigest;
use App\Enums\CaptchaProvider;
use App\Models\User;
use App\Services\Notifications\NightScopeDigestBuilder;
use App\Settings\IntegrationSettings;
use App\Settings\MailSettings;
use App\Settings\NotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationSettingsTest extends TestCase
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
        $this->fakeNotificationSettings();
        $this->actingAs(User::factory()->create(['email' => 'owner@example.com']));
    }

    public function test_settings_page_shows_notification_section(): void
    {
        $this->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('05 · NightScope')
            ->assertSee('Telegram')
            ->assertSee('Rapport hebdomadaire');
    }

    public function test_notification_settings_can_be_saved(): void
    {
        $this->put(route('admin.settings.notifications.update'), $this->validNotificationData([
            'telegram_enabled' => true,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id' => '-1001234567890',
            'alert_daily_conditions' => true,
            'daily_min_score' => 70,
            'daily_time' => '18:30',
        ]))->assertSessionHas('success');

        $settings = app(NotificationSettings::class);
        $this->assertTrue($settings->telegram_enabled);
        $this->assertSame('bot-token-123', $settings->telegram_bot_token);
        $this->assertSame('-1001234567890', $settings->telegram_chat_id);
        $this->assertTrue($settings->alert_daily_conditions);
        $this->assertSame(70, $settings->daily_min_score);
        $this->assertSame('18:30', $settings->daily_time);
    }

    public function test_telegram_requires_credentials_when_enabled(): void
    {
        $this->from(route('admin.settings.edit'))
            ->put(route('admin.settings.notifications.update'), $this->validNotificationData([
                'telegram_enabled' => true,
                'telegram_bot_token' => null,
                'telegram_chat_id' => null,
            ]))
            ->assertSessionHasErrors(['telegram_bot_token', 'telegram_chat_id']);
    }

    public function test_test_notification_sends_telegram_request(): void
    {
        NotificationSettings::fake([
            'telegram_enabled' => true,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id' => '-1001234567890',
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

        Http::preventStrayRequests();
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true]),
        ]);

        $this->post(route('admin.settings.notifications.test'))
            ->assertSessionHas('success');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.telegram.org/botbot-token-123/sendMessage')
            && $request['chat_id'] === '-1001234567890'
            && str_contains((string) $request['text'], 'Canal NightScope opérationnel'));
    }

    public function test_daily_command_sends_digest_when_forced(): void
    {
        NotificationSettings::fake([
            'telegram_enabled' => true,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id' => '-1001234567890',
            'gotify_enabled' => false,
            'gotify_url' => null,
            'gotify_token' => null,
            'ntfy_enabled' => false,
            'ntfy_url' => 'https://ntfy.sh',
            'ntfy_topic' => null,
            'mail_enabled' => false,
            'mail_recipient' => null,
            'alert_daily_conditions' => true,
            'alert_best_targets' => false,
            'alert_weekly_report' => false,
            'alert_task_failures' => true,
            'daily_min_score' => 65,
            'daily_time' => '16:00',
            'weekly_time' => '09:00',
        ]);

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

        $this->mock(NightScopeDigestBuilder::class, function ($mock): void {
            $mock->shouldReceive('daily')->once()->andReturn([
                new NightScopeDigest(
                    title: '🌌 Deepsky — nuit du 17/08',
                    lines: ['⭐ <b>Night score</b>  🟢 <code>80/100</code>'],
                    dedupeKey: 'daily:conditions:2026-08-17',
                ),
            ]);
        });

        $this->artisan('nightscope:notify-daily --force')
            ->expectsOutputToContain('Alerte quotidienne envoyée')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_daily_command_skips_duplicate_digest(): void
    {
        $timezone = config('nightscope.notifications.timezone', 'Europe/Paris');
        $currentTime = now($timezone)->format('H:i');

        NotificationSettings::fake([
            'telegram_enabled' => true,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id' => '-1001234567890',
            'gotify_enabled' => false,
            'gotify_url' => null,
            'gotify_token' => null,
            'ntfy_enabled' => false,
            'ntfy_url' => 'https://ntfy.sh',
            'ntfy_topic' => null,
            'mail_enabled' => false,
            'mail_recipient' => null,
            'alert_daily_conditions' => true,
            'alert_best_targets' => false,
            'alert_weekly_report' => false,
            'alert_task_failures' => true,
            'daily_min_score' => 65,
            'daily_time' => $currentTime,
            'weekly_time' => '09:00',
        ]);

        Cache::put('nightscope:notifications:daily:conditions:2026-08-17', true, now()->addDay());

        Http::fake();

        $this->mock(NightScopeDigestBuilder::class, function ($mock): void {
            $mock->shouldReceive('daily')->twice()->andReturn([
                new NightScopeDigest(
                    title: '🌌 Deepsky — nuit du 17/08',
                    lines: ['⭐ <b>Night score</b>  🟢 <code>80/100</code>'],
                    dedupeKey: 'daily:conditions:2026-08-17',
                ),
            ]);
        });

        $this->artisan('nightscope:notify-daily --force')
            ->expectsOutputToContain('Alerte quotidienne envoyée')
            ->assertSuccessful();

        $this->artisan('nightscope:notify-daily')
            ->expectsOutputToContain('Notification ignorée')
            ->assertSuccessful();

        Http::assertSentCount(1);
    }

    public function test_notification_commands_are_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('nightscope:notify-daily')
            ->expectsOutputToContain('nightscope:notify-weekly')
            ->assertSuccessful();
    }

    /** @param  array<string, mixed>  $overrides */
    private function validNotificationData(array $overrides = []): array
    {
        return array_merge([
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
        ], $overrides);
    }

    private function fakeNotificationSettings(): void
    {
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
    }
}
