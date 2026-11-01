<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com'], 'app.version' => 'v1.3.0', 'app.repository' => 'pokertour/Deepsky']);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }

    private function fakeLatestRelease(string ...$tags): void
    {
        $responses = Http::sequence();
        foreach ($tags as $tag) {
            $responses->push($this->release($tag));
        }

        Http::fake(['api.github.com/repos/pokertour/Deepsky/releases/latest' => $responses->whenEmpty(Http::response($this->release(end($tags))))]);
    }

    /** @return array<string, string> */
    private function release(string $tag): array
    {
        return [
            'tag_name' => $tag,
            'name' => $tag,
            'html_url' => "https://github.com/pokertour/Deepsky/releases/tag/{$tag}",
            'published_at' => '2026-11-02T10:00:00Z',
            'body' => "## What's Changed\n* Nouvelle page À propos <script>alert(1)</script>",
        ];
    }

    public function test_about_page_shows_installed_and_latest_versions_with_an_update_badge(): void
    {
        $this->fakeLatestRelease('v1.4.0');

        $this->actingAs($this->admin())
            ->get(route('admin.about'))
            ->assertOk()
            ->assertSee('v1.3.0')
            ->assertSee('v1.4.0')
            ->assertSee('Nouvelle version disponible')
            ->assertSee('docker compose pull')
            ->assertSee("<h2>What's Changed</h2>", false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('pokertour');

        // La pastille s'affiche ensuite sur toutes les pages, depuis le cache, sans nouvel appel.
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Nouveau');
        Http::assertSentCount(1);
    }

    public function test_no_badge_when_the_installed_version_is_the_latest(): void
    {
        $this->fakeLatestRelease('v1.3.0');

        $this->actingAs($this->admin())
            ->get(route('admin.about'))
            ->assertOk()
            ->assertSee('À jour')
            ->assertDontSee('Nouvelle version disponible');
    }

    public function test_page_still_works_when_github_is_unreachable(): void
    {
        Http::fake(['api.github.com/*' => Http::response('', 503)]);

        $this->actingAs($this->admin())
            ->get(route('admin.about'))
            ->assertOk()
            ->assertSee('Impossible de joindre GitHub pour le moment.');
    }

    public function test_manual_check_and_scheduled_command_refresh_the_cached_release(): void
    {
        $this->fakeLatestRelease('v1.3.0', 'v1.3.1');
        $this->actingAs($this->admin())->get(route('admin.about'))->assertSee('À jour');

        $this->post(route('admin.about.refresh'))
            ->assertRedirect(route('admin.about'))
            ->assertSessionHas('success', 'Nouvelle version disponible : v1.3.1.');

        $this->artisan('app:check-release')->expectsOutputToContain('Nouvelle version disponible : v1.3.1')->assertSuccessful();
    }

    public function test_development_builds_never_show_the_badge(): void
    {
        config(['app.version' => 'local-dev']);
        $this->fakeLatestRelease('v9.9.9');

        $this->actingAs($this->admin())
            ->get(route('admin.about'))
            ->assertOk()
            ->assertSee('Version de développement')
            ->assertDontSee('Nouvelle version disponible')
            ->assertDontSee('À jour');
    }
}
