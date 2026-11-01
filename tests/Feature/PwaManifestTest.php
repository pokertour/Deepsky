<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_manifest_describes_an_installable_app(): void
    {
        $this->get(route('manifest'))
            ->assertOk()
            ->assertJsonPath('name', 'DEEPSKY')
            ->assertJsonPath('short_name', 'Deepsky')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/')
            ->assertJsonPath('theme_color', '#0d1117')
            ->assertJsonFragment(['sizes' => '192x192'])
            ->assertJsonFragment(['sizes' => '512x512']);
    }

    public function test_public_and_admin_pages_link_the_manifest(): void
    {
        $this->withoutVite()->get(route('welcome'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('pwa/apple-touch-icon.png', false);

        $this->withoutVite()->get(route('admin.login'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('apple-mobile-web-app-title', false);
    }

    public function test_service_worker_is_served_at_the_root(): void
    {
        $this->get(route('service-worker'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertSee('deepsky-static-v2', false)
            ->assertSee('/offline.html', false);
    }
}
