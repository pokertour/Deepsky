<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ObservingElevationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['admin.emails' => ['owner@example.com']]);
        Http::preventStrayRequests();
    }

    public function test_it_fetches_and_caches_elevation_and_returns_private_response(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Http::fake(['api.open-meteo.com/v1/elevation*' => Http::response(['elevation' => [42.5]])]);
        $url = route('admin.observing-locations.elevation', ['latitude' => 47.2184, 'longitude' => -1.5536]);
        $this->actingAs($user)->getJson($url)->assertOk()->assertJsonPath('elevation', 42.5);
        $response = $this->getJson($url)->assertOk()->assertJsonPath('elevation', 42.5);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => (float) $request['latitude'] === 47.2184 && (float) $request['longitude'] === -1.5536);
    }

    public function test_it_rejects_invalid_coordinates_without_contacting_provider(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $this->actingAs($user)->getJson(route('admin.observing-locations.elevation', [
            'latitude' => 91, 'longitude' => 'NaN',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        Http::assertNothingSent();
    }

    public function test_upstream_invalid_result_is_unavailable_and_is_not_cached(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Http::fakeSequence()->push(['elevation' => [null]])->push(['elevation' => [25]]);
        $url = route('admin.observing-locations.elevation', ['latitude' => 47, 'longitude' => 1]);
        $this->actingAs($user)->getJson($url)->assertStatus(503);
        $this->getJson($url)->assertOk()->assertJsonPath('elevation', 25);
        Http::assertSentCount(2);
    }

    public function test_only_administrators_can_lookup_elevation(): void
    {
        $url = route('admin.observing-locations.elevation', ['latitude' => 47, 'longitude' => 1]);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($url)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_upstream_server_failure_returns_unavailable_without_exposing_provider_body(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        Http::fake(['api.open-meteo.com/*' => Http::response(['error' => 'Provider internal details'], 500)]);
        $response = $this->actingAs($user)->getJson(route('admin.observing-locations.elevation', [
            'latitude' => 47, 'longitude' => 1,
        ]))->assertStatus(503)->assertDontSee('Provider internal details');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        Http::assertSentCount(3);
    }
}
