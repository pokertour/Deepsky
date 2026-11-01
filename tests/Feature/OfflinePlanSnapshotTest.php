<?php

namespace Tests\Feature;

use App\Models\CelestialObject;
use App\Models\ImagingPlan;
use App\Models\ObservingLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflinePlanSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_a_read_only_snapshot_with_overnight_times(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $admin = User::factory()->create(['email' => 'owner@example.com']);
        $location = ObservingLocation::factory()->create(['timezone' => 'Europe/Paris']);
        $plan = ImagingPlan::factory()->create([
            'date' => '2026-10-04',
            'observing_location_id' => $location->id,
            'equipment_setup_id' => null,
            'notes' => '<script>private notes</script>',
        ]);
        $target = CelestialObject::factory()->create(['ra_deg' => 37.9546, 'dec_deg' => 89.2641]);
        $plan->targets()->create([
            'celestial_object_id' => $target->id,
            'planned_start_at' => '2026-10-04 22:00:00',
            'planned_end_at' => '2026-10-05 02:00:00',
            'position' => 0,
            'score_snapshot' => 85,
        ]);

        $this->get(route('admin.nightscope.plans.show', $plan))->assertRedirect(route('admin.login'));
        $response = $this->actingAs($admin)->get(route('admin.nightscope.plans.show', $plan))
            ->assertOk()->assertSee('Enregistrer hors connexion')->assertSee('/offline.html', false);
        preg_match('/data-offline-plan="([^"]+)"/', $response->getContent(), $matches);
        $snapshot = json_decode(htmlspecialchars_decode($matches[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame($plan->id, $snapshot['id']);
        $this->assertSame('Europe/Paris', $snapshot['timezone']);
        $this->assertSame('04/10 22:00', $snapshot['targets'][0]['start']);
        $this->assertSame('05/10 02:00', $snapshot['targets'][0]['end']);
        $this->assertSame($plan->notes, $snapshot['notes']);
        $response->assertDontSee('<script>private notes</script>', false);
    }
}
