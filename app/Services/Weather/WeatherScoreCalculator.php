<?php

namespace App\Services\Weather;

use App\Data\Astronomy\SolarNight;
use App\Data\Weather\WeatherForecast;
use App\Data\Weather\WeatherPoint;
use App\Data\Weather\WeatherScore;

class WeatherScoreCalculator
{
    public function calculate(WeatherForecast $forecast, SolarNight $night): ?WeatherScore
    {
        if (! $forecast->available() || ! $night->hasAstronomicalNight()) {
            return null;
        }

        $points = collect($forecast->points)->filter(
            fn (WeatherPoint $point): bool => $point->at->betweenIncluded(
                $night->astronomicalNightStart,
                $night->astronomicalNightEnd,
            ),
        );

        if ($points->isEmpty()) {
            return null;
        }

        $clouds = $this->average($points->pluck('cloudCoverPercent')->all());
        $humidity = $this->average($points->pluck('humidityPercent')->all());
        $wind = $this->average($points->pluck('windSpeedKmh')->all());
        $gust = $this->maximum($points->pluck('windGustKmh')->all());
        $precipitation = $this->maximum($points->pluck('precipitationProbabilityPercent')->all());
        $temperature = $this->average($points->pluck('temperatureCelsius')->all());
        $dewPoint = $this->average($points->pluck('dewPointCelsius')->all());
        $visibilityKm = $this->average($points->pluck('visibilityMeters')->all()) / 1000;
        $seeing = $this->nullableAverage($points->pluck('seeingIndex')->all());
        $transparency = $this->nullableAverage($points->pluck('transparencyIndex')->all());
        $dewSpread = max(0, $temperature - $dewPoint);

        $components = [
            'clouds' => 100 - $clouds,
            'humidity' => 100 - $humidity,
            'wind' => max(0, 100 - $wind * 4),
            'precipitation' => 100 - $precipitation,
            'dew' => min(100, $dewSpread / 8 * 100),
            'visibility' => min(100, $visibilityKm / 30 * 100),
        ];
        $weights = [
            'clouds' => 0.40,
            'humidity' => 0.12,
            'wind' => 0.12,
            'precipitation' => 0.08,
            'dew' => 0.08,
            'visibility' => 0.05,
        ];

        if ($seeing !== null) {
            $components['seeing'] = $this->astroQuality($seeing);
            $weights['seeing'] = 0.08;
        }

        if ($transparency !== null) {
            $components['transparency'] = $this->astroQuality($transparency);
            $weights['transparency'] = 0.07;
        }

        $totalWeight = array_sum($weights);
        $score = (int) round(array_sum(array_map(
            fn (float $value, string $key): float => $value * $weights[$key],
            $components,
            array_keys($components),
        )) / max(0.0001, $totalWeight));

        return new WeatherScore(
            score: max(0, min(100, $score)),
            label: $this->label($score),
            averageCloudCoverPercent: $clouds,
            averageHumidityPercent: $humidity,
            averageWindSpeedKmh: $wind,
            maximumWindGustKmh: $gust,
            precipitationRiskPercent: $precipitation,
            averageTemperatureCelsius: $temperature,
            averageDewPointCelsius: $dewPoint,
            averageVisibilityKm: $visibilityKm,
            averageSeeingIndex: $seeing,
            averageTransparencyIndex: $transparency,
        );
    }

    /** 7Timer indexes: 1 = excellent, 8 = poor. */
    private function astroQuality(float $index): float
    {
        return max(0, min(100, (9 - $index) / 8 * 100));
    }

    /** @param array<int, float|int|null> $values */
    private function average(array $values): float
    {
        $values = array_values(array_filter($values, is_numeric(...)));

        return $values === [] ? 0 : array_sum($values) / count($values);
    }

    /** @param array<int, float|int|null> $values */
    private function nullableAverage(array $values): ?float
    {
        $values = array_values(array_filter($values, is_numeric(...)));

        return $values === [] ? null : array_sum($values) / count($values);
    }

    /** @param array<int, float|int|null> $values */
    private function maximum(array $values): float
    {
        $values = array_values(array_filter($values, is_numeric(...)));

        return $values === [] ? 0 : max($values);
    }

    private function label(int $score): string
    {
        return match (true) {
            $score >= 85 => __('Excellente'),
            $score >= 70 => __('Très bonne'),
            $score >= 55 => __('Bonne'),
            $score >= 40 => __('Moyenne'),
            $score >= 20 => __('Mauvaise'),
            default => __('Impossible'),
        };
    }
}
