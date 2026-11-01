<?php

namespace Tests\Unit;

use App\Support\LightTravel;
use Tests\TestCase;

class LightTravelTest extends TestCase
{
    public function test_distances_become_a_light_age_and_an_earth_epoch(): void
    {
        $this->assertSame(['duration' => '2,5 millions d’années', 'epoch' => 'au temps des premiers outils de pierre'], LightTravel::describe(2_537_000));
        $this->assertSame(['duration' => "1\u{202F}340 ans", 'epoch' => 'au temps de l’Empire romain'], LightTravel::describe(1_344));
        $this->assertSame('23 millions d’années', LightTravel::duration(23_000_000));
        $this->assertSame('quand les mammifères prenaient la place des dinosaures', LightTravel::epoch(23_000_000));
        $this->assertSame('avant la naissance du Système solaire', LightTravel::epoch(10_000_000_000));
        $this->assertNull(LightTravel::describe(null));
    }

    public function test_light_ages_are_written_in_english_for_english_visitors(): void
    {
        app()->setLocale('en');

        $this->assertSame(['duration' => '2.5 million years', 'epoch' => 'in the days of the first stone tools'], LightTravel::describe(2_537_000));
        $this->assertSame('1,340 years', LightTravel::duration(1_344));
        $this->assertSame('1 billion years', LightTravel::duration(1_000_000_000));
    }
}
