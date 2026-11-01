<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GalleryOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_summarises_published_photos_only(): void
    {
        Astrophoto::factory()->create(['target' => 'M 31', 'exposure' => '7200', 'captured_at' => '2024-09-01']);
        Astrophoto::factory()->create(['target' => 'M 31', 'exposure' => '3600', 'captured_at' => '2025-01-10']);
        Astrophoto::factory()->create(['target' => 'M 42', 'exposure' => '1800', 'captured_at' => '2025-02-01']);
        Astrophoto::factory()->create(['target' => 'M 51', 'exposure' => '90000', 'captured_at' => '2020-01-01', 'is_published' => false]);

        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('Le portfolio en chiffres')
            ->assertSeeInOrder(['3', 'images', '4', 'heures de pose', '2', 'cibles', '2024', 'depuis']);
    }

    public function test_summary_is_hidden_on_filtered_or_paginated_views(): void
    {
        Astrophoto::factory()->create();

        $this->get(route('welcome', ['search' => 'M']))->assertOk()->assertDontSee('Le portfolio en chiffres');
        $this->get(route('welcome', ['page' => 2]))->assertOk()->assertDontSee('Le portfolio en chiffres');
    }
}
