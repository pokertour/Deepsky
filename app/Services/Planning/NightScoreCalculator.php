<?php

namespace App\Services\Planning;

use App\Data\Astronomy\MoonData;
use App\Data\Astronomy\SolarNight;
use App\Data\Planning\NightScore;
use App\Data\Weather\WeatherScore;

class NightScoreCalculator
{
    public function calculate(SolarNight $night, MoonData $moon, ?WeatherScore $weather = null): NightScore
    {
        $minutes = $night->hasAstronomicalNight()
            ? (int) $night->astronomicalNightStart->diffInMinutes($night->astronomicalNightEnd)
            : 0;
        $darknessQuality = min(100, $minutes / 480 * 100);
        $moonQuality = 100 - $moon->illuminationPercent;
        $weights = $this->weights();
        $score = $weather
            ? $darknessQuality * $weights['darkness']
                + $moonQuality * $weights['moon']
                + $weather->score * $weights['weather']
            : $darknessQuality * $weights['darkness_without_weather']
                + $moonQuality * $weights['moon_without_weather'];

        return new NightScore(
            score: max(0, min(100, (int) round($score))),
            darknessMinutes: $minutes,
            moonIlluminationPercent: $moon->illuminationPercent,
            weatherScore: $weather?->score,
        );
    }

    /** @return array{darkness_without_weather: float, moon_without_weather: float, darkness: float, moon: float, weather: float} */
    private function weights(): array
    {
        $defaults = [
            'darkness_without_weather' => 0.60,
            'moon_without_weather' => 0.40,
            'darkness' => 0.35,
            'moon' => 0.20,
            'weather' => 0.45,
        ];

        if (! function_exists('app') || ! app()->bound('config')) {
            return $defaults;
        }

        return array_replace($defaults, (array) config('nightscope.scores.night', []));
    }
}
