<?php

namespace Tests\Feature;

use App\Models\Astrophoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AstrophotoVersionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_payload_lists_other_versions_of_the_same_target(): void
    {
        $old = Astrophoto::factory()->create([
            'target' => 'M 31',
            'captured_at' => '2025-09-15',
        ]);

        Astrophoto::factory()->create([
            'target' => 'M 31',
            'captured_at' => '2026-06-20',
        ]);

        $response = $this->get(route('welcome'))->assertOk();

        // Chaque card doit référencer l'autre version dans son payload JSON
        $response->assertSee('&quot;versions&quot;:[{&quot;id&quot;:'.$old->id, false);
        $response->assertSee('15\/09\/2025', false);
    }

    public function test_card_payload_has_empty_versions_for_unique_target(): void
    {
        Astrophoto::factory()->create(['target' => 'NGC 7000']);

        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('&quot;versions&quot;:[]', false);
    }
}
