<?php

namespace Tests\Unit;

use App\Data\Astronomy\MoonData;
use App\Data\Notifications\NightScopeDigest;
use App\Data\Weather\WeatherScore;
use App\Services\Notifications\NightScopeDigestFormatter;
use PHPUnit\Framework\TestCase;

class NightScopeDigestFormatterTest extends TestCase
{
    public function test_score_emoji_uses_traffic_light_colors(): void
    {
        $formatter = new NightScopeDigestFormatter;

        $this->assertSame('🟢', $formatter->scoreEmoji(75));
        $this->assertSame('🟡', $formatter->scoreEmoji(50));
        $this->assertSame('🔴', $formatter->scoreEmoji(49));
    }

    public function test_moon_emoji_follows_illumination(): void
    {
        $formatter = new NightScopeDigestFormatter;

        $this->assertSame('🌑', $formatter->moonEmoji($this->moon(2)));
        $this->assertSame('🌓', $formatter->moonEmoji($this->moon(40)));
        $this->assertSame('🌕', $formatter->moonEmoji($this->moon(90)));
    }

    public function test_weather_emoji_prefers_precipitation_then_clouds(): void
    {
        $formatter = new NightScopeDigestFormatter;

        $this->assertSame('🌧️', $formatter->weatherEmoji($this->weather(score: 20, clouds: 10, precip: 80)));
        $this->assertSame('☁️', $formatter->weatherEmoji($this->weather(score: 40, clouds: 80, precip: 0)));
        $this->assertSame('✨', $formatter->weatherEmoji($this->weather(score: 88, clouds: 10, precip: 0)));
    }

    public function test_digest_strips_html_for_plain_channels(): void
    {
        $digest = new NightScopeDigest(
            title: '🎯 Deepsky — top cibles',
            lines: ['🥇 <b>NGC 7000</b>  🟢 <code>82/100</code>'],
            dedupeKey: 'daily:targets:2026-08-17',
        );

        $this->assertSame('🥇 NGC 7000  🟢 82/100', $digest->body());
        $this->assertStringContainsString('<b>🎯 Deepsky — top cibles</b>', $digest->telegramHtml());
    }

    private function moon(float $illumination): MoonData
    {
        return new MoonData(
            rightAscensionDeg: 0,
            declinationDeg: 0,
            altitudeDeg: 10,
            azimuthDeg: 180,
            illuminationPercent: $illumination,
            phaseFraction: $illumination / 100,
        );
    }

    private function weather(int $score, float $clouds, float $precip): WeatherScore
    {
        return new WeatherScore(
            score: $score,
            label: 'Test',
            averageCloudCoverPercent: $clouds,
            averageHumidityPercent: 50,
            averageWindSpeedKmh: 10,
            maximumWindGustKmh: 15,
            precipitationRiskPercent: $precip,
            averageTemperatureCelsius: 12,
            averageDewPointCelsius: 8,
            averageVisibilityKm: 20,
        );
    }
}
