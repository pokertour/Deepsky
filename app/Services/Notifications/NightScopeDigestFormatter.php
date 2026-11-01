<?php

namespace App\Services\Notifications;

use App\Data\Astronomy\MoonData;
use App\Data\Weather\WeatherScore;

class NightScopeDigestFormatter
{
    public function scoreEmoji(int $score): string
    {
        return match (true) {
            $score >= 75 => '🟢',
            $score >= 50 => '🟡',
            default => '🔴',
        };
    }

    public function scoreHtml(int $score): string
    {
        return $this->scoreEmoji($score).' <code>'.$score.'/100</code>';
    }

    public function moonEmoji(MoonData $moon): string
    {
        $illumination = $moon->illuminationPercent;

        return match (true) {
            $illumination < 5 => '🌑',
            $illumination < 25 => '🌒',
            $illumination < 50 => '🌓',
            $illumination < 75 => '🌔',
            default => '🌕',
        };
    }

    public function weatherEmoji(WeatherScore $weather): string
    {
        return match (true) {
            $weather->precipitationRiskPercent >= 40 => '🌧️',
            $weather->averageCloudCoverPercent >= 70 => '☁️',
            $weather->averageCloudCoverPercent >= 40 => '⛅',
            $weather->score >= 75 => '✨',
            default => '🌤️',
        };
    }

    public function medal(int $rank): string
    {
        return match ($rank) {
            1 => '🥇',
            2 => '🥈',
            3 => '🥉',
            default => '🔹',
        };
    }

    public function metaLine(string $emoji, string $label, string $value): string
    {
        return $emoji.' <b>'.e($label).'</b> '.e($value);
    }

    public function targetLine(int $rank, string $name, int $score): string
    {
        return $this->medal($rank).' <b>'.e($name).'</b>  '.$this->scoreHtml($score);
    }
}
