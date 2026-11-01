<?php

namespace App\Console\Commands;

use App\Models\ObservingLocation;
use App\Services\Weather\WeatherManager;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class WarmNightScopeWeather extends Command
{
    protected $signature = 'nightscope:warm-weather';

    protected $description = 'Met en cache les prévisions NightScope des lieux d’observation';

    public function handle(WeatherManager $weather): int
    {
        $count = 0;

        ObservingLocation::query()->each(function (ObservingLocation $location) use ($weather, &$count): void {
            $weather->forecast($location, CarbonImmutable::now($location->timezone)->startOfDay(), 8);
            $count++;
        });

        $this->info("Prévisions mises en cache pour {$count} lieu(x).");

        return self::SUCCESS;
    }
}
