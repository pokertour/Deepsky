<?php

namespace Tests\Feature;

use App\Http\Controllers\AstrophotoController;
use App\Models\Astrophoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AstrophotoViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_recording_a_view_increments_the_counter(): void
    {
        $photo = Astrophoto::factory()->create(['views_count' => 0]);

        $this->post(route('astrophoto.click', $photo))
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertSame(1, $photo->fresh()->views_count);
    }

    public function test_bot_views_are_ignored(): void
    {
        $photo = Astrophoto::factory()->create(['views_count' => 0]);

        $this->withHeader('User-Agent', 'facebookexternalhit/1.1')
            ->post(route('astrophoto.click', $photo))
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        $this->assertSame(0, $photo->fresh()->views_count);
    }

    public function test_repeated_views_from_the_same_visitor_are_throttled(): void
    {
        $photo = Astrophoto::factory()->create(['views_count' => 0]);

        // Une seule instance de requête => même empreinte (IP + session + user agent),
        // ce qui reproduit fidèlement un visiteur unique qui reclique.
        $request = Request::create(route('astrophoto.click', $photo), 'POST');
        $request->setLaravelSession($this->app['session']->driver());

        $controller = new AstrophotoController;

        $this->assertSame(200, $controller->recordView($request, $photo)->getStatusCode());
        $this->assertSame(429, $controller->recordView($request, $photo)->getStatusCode());

        $this->assertSame(1, $photo->fresh()->views_count);
    }

    public function test_full_resolution_page_renders(): void
    {
        $photo = Astrophoto::factory()->create();

        $this->withoutVite()
            ->get(route('astrophoto.full', $photo))
            ->assertOk()
            ->assertSee($photo->title);
    }
}
