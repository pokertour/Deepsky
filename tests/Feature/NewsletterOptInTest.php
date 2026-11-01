<?php

namespace Tests\Feature;

use App\Enums\CaptchaProvider;
use App\Models\Astrophoto;
use App\Settings\IntegrationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterOptInTest extends TestCase
{
    use RefreshDatabase;

    public function test_photo_has_one_accessible_opt_in_before_the_footer(): void
    {
        $photo = Astrophoto::factory()->create();
        $response = $this->get(route('astrophoto.show', $photo))->assertOk()
            ->assertSee('Recevoir les prochaines photos')
            ->assertSee('<label for="newsletter-email"', false)
            ->assertSee('autocomplete="email"', false)
            ->assertSee('aria-describedby="newsletter-help newsletter-privacy"', false);
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="newsletter-form"'));
        $this->assertLessThan(strpos($html, '<footer'), strpos($html, 'id="newsletter-form"'));
    }

    public function test_gallery_keeps_one_opt_in_in_the_footer(): void
    {
        $html = $this->get(route('welcome'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'id="newsletter-form"'));
        $this->assertGreaterThan(strpos($html, '<footer'), strpos($html, 'id="newsletter-form"'));
    }

    public function test_validation_preserves_input_and_associates_the_error_with_the_email_field(): void
    {
        $this->from(route('welcome'))->post(route('newsletter.subscribe'), ['email' => 'incorrect'])
            ->assertRedirect(route('welcome'))->assertSessionHasErrors('email')->assertSessionHasInput('email', 'incorrect');
        $this->get(route('welcome'))->assertOk()
            ->assertSee('value="incorrect"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="newsletter-help newsletter-privacy newsletter-error"', false)
            ->assertSee('id="newsletter-error" role="alert"', false);
    }

    public function test_status_and_turnstile_widget_are_rendered_once_on_the_photo_page(): void
    {
        $settings = app(IntegrationSettings::class);
        $settings->captcha_provider = CaptchaProvider::Turnstile;
        $settings->captcha_site_key = 'site-key';
        $settings->captcha_secret_key = 'secret-key';
        $settings->save();
        $photo = Astrophoto::factory()->create();

        $html = $this->withSession(['newsletter_status' => 'Confirmation envoyée'])
            ->get(route('astrophoto.show', $photo))->assertOk()->assertSee('role="status"', false)->getContent();
        $this->assertSame(1, substr_count($html, 'data-action="newsletter"'));
        $this->assertSame(1, substr_count($html, CaptchaProvider::Turnstile->scriptUrl()));
        $this->assertStringNotContainsString('secret-key', $html);
    }
}
