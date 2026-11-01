<?php

namespace Tests\Unit;

use App\Data\Astronomy\MoonData;
use App\Data\Notifications\NightScopeDigest;
use App\Data\Planning\NightScore;
use App\Data\Planning\TonightData;
use App\Models\ObservingLocation;
use App\Services\Notifications\NightScopeDigestBuilder;
use App\Services\Notifications\NightScopeDigestFormatter;
use App\Services\Planning\TonightService;
use App\Services\Weather\ForecastService;
use App\Settings\NotificationSettings;
use Mockery;
use Tests\TestCase;

class NightScopeDigestBuilderTest extends TestCase
{
    public function test_top_targets_digest_requires_favorable_night(): void
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
            'alert_daily_conditions' => true,
            'alert_best_targets' => true,
            'alert_weekly_report' => false,
            'alert_task_failures' => true,
            'alert_security_events' => true,
            'alert_every_login' => true,
            'locale' => 'fr',
            'daily_min_score' => 65,
            'daily_time' => '16:00',
            'weekly_time' => '09:00',
        ]);

        $location = new ObservingLocation(['name' => 'Maison']);
        $location->id = 1;

        $tonight = Mockery::mock(TonightService::class);
        $tonight->shouldReceive('build')->andReturn(new TonightData(
            date: now()->toImmutable()->startOfDay(),
            location: $location,
            equipmentSetup: null,
            optics: null,
            night: null,
            moon: new MoonData(0, 0, 10, 180, 20, 0.2),
            weather: null,
            nightScore: new NightScore(score: 40, darknessMinutes: 300, moonIlluminationPercent: 20, weatherScore: null),
            targets: [],
        ));

        $builder = new NightScopeDigestBuilder(
            tonight: $tonight,
            forecast: Mockery::mock(ForecastService::class),
            settings: app(NotificationSettings::class),
            formatter: new NightScopeDigestFormatter,
        );

        $this->assertSame([], $builder->daily());
    }

    public function test_preview_can_still_send_under_threshold_digests(): void
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
            'alert_daily_conditions' => true,
            'alert_best_targets' => true,
            'alert_weekly_report' => false,
            'alert_task_failures' => true,
            'alert_security_events' => true,
            'alert_every_login' => true,
            'locale' => 'fr',
            'daily_min_score' => 65,
            'daily_time' => '16:00',
            'weekly_time' => '09:00',
        ]);

        $location = new ObservingLocation(['name' => 'Maison']);
        $location->id = 1;

        $tonight = Mockery::mock(TonightService::class);
        $tonight->shouldReceive('build')->andReturn(new TonightData(
            date: now()->toImmutable()->startOfDay(),
            location: $location,
            equipmentSetup: null,
            optics: null,
            night: null,
            moon: new MoonData(0, 0, 10, 180, 20, 0.2),
            weather: null,
            nightScore: new NightScore(score: 40, darknessMinutes: 300, moonIlluminationPercent: 20, weatherScore: null),
            targets: [],
        ));

        $builder = new NightScopeDigestBuilder(
            tonight: $tonight,
            forecast: Mockery::mock(ForecastService::class),
            settings: app(NotificationSettings::class),
            formatter: new NightScopeDigestFormatter,
        );

        $digests = $builder->daily(preview: true);

        $this->assertNotEmpty($digests);
        $this->assertInstanceOf(NightScopeDigest::class, $digests[0]);
        $this->assertStringContainsString('sous le seuil', $digests[0]->htmlBody());
    }
}
