<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FrenchLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_use_french_rules_and_attribute_names(): void
    {
        $this->from(route('welcome'))
            ->post(route('newsletter.subscribe'), ['email' => 'pas-une-adresse'])
            ->assertSessionHasErrors(['email' => 'Le champ adresse email doit être une adresse email valide.']);
    }

    public function test_relative_dates_are_written_in_french(): void
    {
        $this->assertSame('il y a 2 heures', Carbon::now()->subHours(2)->diffForHumans());
    }
}
