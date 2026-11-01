<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_is_valid_xml_and_contains_only_public_pages_and_published_photos(): void
    {
        $published = Astrophoto::factory()->create();
        $draft = Astrophoto::factory()->create(['is_published' => false]);
        $response = $this->get(route('sitemap'))->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);
        $xml->registerXPathNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $urls = array_map(fn ($url): string => (string) $url, $xml->xpath('//s:loc'));
        $pages = [route('welcome'), route('observatory'), route('time-machine'), route('astrophoto.show', $published)];
        $this->assertSame(collect($pages)->flatMap(fn (string $page): array => [$page, $page.'?lang=en'])->all(), $urls);
        $response->assertDontSee(route('astrophoto.show', $draft), false);
    }

    public function test_robots_declares_the_sitemap_on_the_current_host(): void
    {
        $this->get(route('robots'))->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.route('sitemap'), false);
        $this->assertFileDoesNotExist(public_path('robots.txt'));
    }

    public function test_public_pages_have_distinct_titles_and_canonicals_without_tracking_parameters(): void
    {
        $photo = Astrophoto::factory()->create(['title' => 'M51 — Galaxie du Tourbillon']);

        $this->get(route('welcome', ['utm_source' => 'newsletter']))->assertOk()
            ->assertSee('<title>Deepsky — Astrophotographie du ciel profond à Nantes</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('welcome').'">', false);
        $this->get(route('observatory'))->assertOk()
            ->assertSee('<title>Observatoire — Le ciel de Nantes en direct | Deepsky</title>', false);
        $this->get(route('astrophoto.show', [$photo, 'utm_source' => 'newsletter']))->assertOk()
            ->assertSee('<title>M51 — Galaxie du Tourbillon — Astrophotographie | Deepsky</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('astrophoto.show', $photo).'">', false);
        $this->get(route('astrophoto.full', [$photo, 'starless' => 1]))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('astrophoto.show', $photo).'">', false);
    }

    public function test_pagination_keeps_its_own_canonical_and_filtered_gallery_is_not_indexed(): void
    {
        $this->get(route('welcome', ['page' => 2, 'utm_source' => 'test']))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('welcome', ['page' => 2]).'">', false);
        $this->get(route('welcome', ['search' => 'M51']))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('welcome', ['search' => 'M51']).'">', false);
    }

    public function test_newsletter_status_pages_are_not_indexed(): void
    {
        $subscriber = NewsletterSubscriber::factory()->unconfirmed()->create();
        $this->get(route('newsletter.confirm', $subscriber->confirmation_token))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_observatory_canonical_uses_only_an_available_year(): void
    {
        Astrophoto::factory()->create(['captured_at' => '2025-06-01']);

        $this->get(route('observatory', ['year' => 2025, 'utm_source' => 'test']))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('observatory', ['year' => 2025]).'">', false);
        $this->get(route('observatory', ['year' => 2200]))->assertOk()
            ->assertSee('<link rel="canonical" href="'.route('observatory').'">', false);
    }

    public function test_photo_title_is_escaped(): void
    {
        $photo = Astrophoto::factory()->create(['title' => '<script>alert(1)</script>']);
        $this->get(route('astrophoto.show', $photo))->assertOk()
            ->assertSee('<title>&lt;script&gt;alert(1)&lt;/script&gt; — Astrophotographie | Deepsky</title>', false);
    }
}
