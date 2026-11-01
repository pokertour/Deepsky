<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use App\Models\SetupItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObservatoryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_observatory_page_renders_without_photos(): void
    {
        $this->get(route('observatory'))
            ->assertOk()
            ->assertSee('MISSION_LOG');
    }

    public function test_observatory_page_shows_aggregated_stats(): void
    {
        $tube = SetupItem::factory()->create(['type' => 'optical_tube', 'name' => 'Askar 71F']);

        Astrophoto::factory()->create([
            'target' => 'M 101',
            'exposure' => 7200,
            'filters' => 'SHO',
            'optical_tube_id' => $tube->id,
            'captured_at' => now()->subDays(10),
        ]);

        Astrophoto::factory()->create([
            'target' => 'M 31',
            'exposure' => 3600,
            'filters' => 'L-RGB',
            'optical_tube_id' => $tube->id,
            'captured_at' => now()->subDays(20),
        ]);

        $this->get(route('observatory'))
            ->assertOk()
            // 7200 + 3600 secondes = 3 heures d'intégration
            ->assertSee('3h')
            ->assertSee('SHO')
            ->assertSee('L-RGB')
            ->assertSee('M 101')
            ->assertSee('M 31')
            ->assertSee('Askar 71F');
    }

    public function test_observatory_page_can_be_filtered_by_year(): void
    {
        Astrophoto::factory()->create([
            'target' => 'M 101',
            'exposure' => 7200,
            'captured_at' => now()->setDate(now()->year - 1, 6, 15),
        ]);

        Astrophoto::factory()->create([
            'target' => 'M 31',
            'exposure' => 3600,
            'captured_at' => now()->subDay(),
        ]);

        $this->get(route('observatory', ['year' => now()->year - 1]))
            ->assertOk()
            ->assertSee('M 101')
            ->assertDontSee('M 31');
    }

    public function test_observatory_page_shows_year_filter_links(): void
    {
        Astrophoto::factory()->create(['captured_at' => now()->subDay()]);

        $this->get(route('observatory'))
            ->assertOk()
            ->assertSee(route('observatory', ['year' => now()->year]));
    }

    public function test_observatory_page_ignores_a_year_without_photos(): void
    {
        Astrophoto::factory()->create([
            'target' => 'M 31',
            'captured_at' => now()->subDay(),
        ]);

        $this->get(route('observatory', ['year' => 1999]))
            ->assertOk()
            ->assertSee('M 31');
    }

    public function test_gallery_links_to_observatory(): void
    {
        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee(route('observatory'));
    }
}
