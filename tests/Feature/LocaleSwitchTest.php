<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_french_is_the_default_with_alternates_for_each_language(): void
    {
        $this->get(route('observatory'))
            ->assertOk()
            ->assertHeader('Content-Language', 'fr')
            ->assertSee('<html lang="fr">', false)
            ->assertSee('<link rel="canonical" href="'.route('observatory').'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.route('observatory').'?lang=en">', false)
            ->assertSee('Observatoire');
    }

    public function test_lang_parameter_switches_to_english_and_is_remembered(): void
    {
        $this->get(route('observatory', ['lang' => 'en']))
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertSee('<html lang="en">', false)
            ->assertSee('<link rel="canonical" href="'.route('observatory').'?lang=en">', false)
            ->assertSee('Observatory')
            ->assertCookie(SetLocale::COOKIE, 'en', encrypted: false);

        $this->withUnencryptedCookie(SetLocale::COOKIE, 'en')
            ->get(route('time-machine'))
            ->assertHeader('Content-Language', 'en')
            ->assertSee('Back in time');
    }

    public function test_browser_language_is_used_without_explicit_choice_and_unknown_languages_fall_back_to_french(): void
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')->get(route('observatory'))->assertHeader('Content-Language', 'en');
        $this->withHeader('Accept-Language', 'de-DE')->get(route('observatory', ['lang' => 'de']))->assertHeader('Content-Language', 'fr');
    }

    public function test_validation_messages_follow_the_selected_language(): void
    {
        $this->withUnencryptedCookie(SetLocale::COOKIE, 'en')
            ->from(route('welcome'))
            ->post(route('newsletter.subscribe'), ['email' => 'not-an-address'])
            ->assertSessionHasErrors(['email' => 'The email field must be a valid email address.']);
    }
}
