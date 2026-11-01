<?php

namespace Tests\Feature;

use App\Contracts\Weather\WeatherProvider;
use App\Data\Weather\WeatherForecast;
use App\Data\Weather\WeatherPoint;
use App\Enums\ImagingPlanStatus;
use App\Models\CelestialObject;
use App\Models\EquipmentSetup;
use App\Models\ImagingPlan;
use App\Models\ObservingLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NightPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    public function test_admin_can_generate_save_and_start_an_imaging_plan(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        CelestialObject::factory()->count(3)->sequence(
            ['catalog' => 'M', 'catalog_number' => 'PLAN-1', 'ra_deg' => 37.9546, 'dec_deg' => 89.2641, 'magnitude' => 4, 'angular_width_arcmin' => 60, 'angular_height_arcmin' => 60],
            ['catalog_number' => 'PLAN-2', 'ra_deg' => 52.0, 'dec_deg' => 80.0, 'magnitude' => 5],
            ['catalog_number' => 'PLAN-3', 'ra_deg' => 65.0, 'dec_deg' => 75.0, 'magnitude' => 6],
        )->create();

        $this->actingAs($user)
            ->get(route('admin.nightscope.planner', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('Proposition automatique')
            ->assertSee('PLAN-1')
            ->assertSee('Ajouter une cible');

        $planOneId = (int) CelestialObject::query()->where('catalog_number', 'PLAN-1')->value('id');

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.draft.exclude'), [
                'date' => '2026-08-03',
                'celestial_object_id' => $planOneId,
            ])
            ->assertRedirect(route('admin.nightscope.planner', ['date' => '2026-08-03']));

        $this->actingAs($user)
            ->get(route('admin.nightscope.planner', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertDontSee('>PLAN-1<', false);

        $manual = CelestialObject::factory()->create([
            'catalog_number' => 'DRAFT-ADD',
            'ra_deg' => 70.0,
            'dec_deg' => 85.0,
            'magnitude' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.draft.targets'), [
                'date' => '2026-08-03',
                'celestial_object_id' => $manual->id,
                'planned_start' => '22:00',
                'planned_end' => '23:00',
            ])
            ->assertRedirect(route('admin.nightscope.planner', ['date' => '2026-08-03']));

        $this->actingAs($user)
            ->get(route('admin.nightscope.planner', ['date' => '2026-08-03']))
            ->assertOk()
            ->assertSee('DRAFT-ADD');

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.store'), ['date' => '2026-08-03', 'notes' => 'Test plan'])
            ->assertRedirect();

        $plan = ImagingPlan::query()->with('targets')->firstOrFail();
        $this->assertNotEmpty($plan->targets);
        $this->assertTrue($plan->targets->contains(fn ($target): bool => $target->celestial_object_id === $manual->id));
        $this->assertFalse($plan->targets->contains(fn ($target): bool => $target->celestial_object_id === $planOneId));

        $this->actingAs($user)->post(route('admin.nightscope.plans.start', $plan))->assertSessionHas('success');

        $this->assertSame(ImagingPlanStatus::Active, $plan->fresh()->status);
        $this->assertNotNull($plan->fresh()->imagingSession);

        $this->actingAs($user)
            ->get(route('admin.astrophotos.create'))
            ->assertOk()
            ->assertSee('Session NightScope')
            ->assertSee('En cours');
    }

    public function test_adding_a_draft_target_requires_an_observing_location(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $target = CelestialObject::factory()->create();

        $this->actingAs($user)
            ->from(route('admin.nightscope.planner', ['date' => '2026-08-03']))
            ->post(route('admin.nightscope.planner.draft.targets'), [
                'date' => '2026-08-03',
                'celestial_object_id' => $target->id,
                'planned_start' => '22:00',
                'planned_end' => '23:00',
            ])
            ->assertRedirect(route('admin.nightscope.planner', ['date' => '2026-08-03']))
            ->assertSessionHas('error', 'Configure un lieu d’observation avant d’ajouter une cible.')
            ->assertSessionHasInput('celestial_object_id', $target->id);
    }

    public function test_starting_a_session_stores_weather_snapshot_when_available(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $location = ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        CelestialObject::factory()->create([
            'catalog_number' => 'WX-1',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 4,
        ]);

        $this->app->instance(WeatherProvider::class, new class implements WeatherProvider
        {
            public function forecast(ObservingLocation $location, CarbonImmutable $from, int $days): WeatherForecast
            {
                $points = [];

                for ($hour = 0; $hour < 24 * 8; $hour++) {
                    $points[] = new WeatherPoint($from->addHours($hour), 10, 5, 5, 5, 60, 12, 6, 5, 10, 0, 30000, 2, 3);
                }

                return new WeatherForecast('fake', $from, $points);
            }
        });

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.store'), ['date' => '2026-08-03'])
            ->assertRedirect();

        $plan = ImagingPlan::query()->firstOrFail();
        $this->actingAs($user)->post(route('admin.nightscope.plans.start', $plan))->assertSessionHas('success');

        $session = $plan->fresh()->imagingSession;
        $this->assertNotNull($session?->weather_data);
        $this->assertEquals(2.0, $session->weather_data['seeing']);
        $this->assertSame($location->id, $session->observing_location_id);
    }

    public function test_admin_can_duplicate_reorder_and_delete_plan_targets(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $targets = CelestialObject::factory()->count(2)->sequence(
            ['catalog_number' => 'ORD-1', 'ra_deg' => 37.9546, 'dec_deg' => 89.2641, 'magnitude' => 4],
            ['catalog_number' => 'ORD-2', 'ra_deg' => 52.0, 'dec_deg' => 80.0, 'magnitude' => 5],
        )->create();

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.store'), ['date' => '2026-08-03', 'notes' => 'Base'])
            ->assertRedirect();

        $plan = ImagingPlan::query()->with('targets')->firstOrFail();
        $this->assertGreaterThanOrEqual(1, $plan->targets->count());

        if ($plan->targets->count() >= 2) {
            $first = $plan->targets->first();
            $second = $plan->targets->skip(1)->first();

            $this->actingAs($user)
                ->post(route('admin.nightscope.plans.targets.down', [$plan, $first]))
                ->assertRedirect();

            $this->assertSame($second->id, $plan->fresh()->targets->first()->id);
        }

        $this->actingAs($user)
            ->put(route('admin.nightscope.plans.update', $plan), [
                'date' => $plan->date->format('Y-m-d'),
                'observing_location_id' => $plan->observing_location_id,
                'equipment_setup_id' => $plan->equipment_setup_id,
                'notes' => 'Notes mises à jour',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame('Notes mises à jour', $plan->fresh()->notes);

        $this->actingAs($user)
            ->post(route('admin.nightscope.plans.duplicate', $plan), ['date' => '2026-08-10'])
            ->assertRedirect();

        $copy = ImagingPlan::query()->whereDate('date', '2026-08-10')->firstOrFail();
        $this->assertSame($plan->targets->count(), $copy->targets()->count());
        $this->assertSame(ImagingPlanStatus::Planned, $copy->status);

        $targetToRemove = $copy->targets()->first();
        $this->actingAs($user)
            ->delete(route('admin.nightscope.plans.targets.destroy', [$copy, $targetToRemove]))
            ->assertRedirect();
        $this->assertDatabaseMissing('imaging_plan_targets', ['id' => $targetToRemove->id]);

        $this->actingAs($user)
            ->delete(route('admin.nightscope.plans.destroy', $copy))
            ->assertRedirect(route('admin.nightscope.planner'));
        $this->assertDatabaseMissing('imaging_plans', ['id' => $copy->id]);
    }

    public function test_admin_can_manually_add_a_target_to_a_saved_plan(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        ObservingLocation::factory()->default()->create([
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        CelestialObject::factory()->create([
            'catalog' => 'M',
            'catalog_number' => 'AUTO-1',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 4,
            'angular_width_arcmin' => 60,
            'angular_height_arcmin' => 60,
        ]);
        $manual = CelestialObject::factory()->create([
            'catalog' => 'NGC',
            'catalog_number' => 'MANUAL-42',
            'name' => 'Cible manuelle',
            'ra_deg' => 52.0,
            'dec_deg' => 80.0,
            'magnitude' => 5,
        ]);

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.store'), ['date' => '2026-08-03'])
            ->assertRedirect();

        $plan = ImagingPlan::query()->firstOrFail();

        $this->actingAs($user)
            ->post(route('admin.nightscope.plans.targets.store', $plan), [
                'celestial_object_id' => $manual->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('imaging_plan_targets', [
            'imaging_plan_id' => $plan->id,
            'celestial_object_id' => $manual->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.nightscope.plans.show', $plan))
            ->assertOk()
            ->assertSee('Ajouter une cible')
            ->assertSee('Enregistrer le plan')
            ->assertSee('Sauver horaires')
            ->assertSee('MANUAL-42');
    }

    public function test_admin_can_edit_plan_config_and_target_times(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $home = ObservingLocation::factory()->default()->create([
            'name' => 'Maison',
            'latitude' => 47.2184,
            'longitude' => -1.5536,
            'timezone' => 'Europe/Paris',
        ]);
        $dark = ObservingLocation::factory()->create([
            'name' => 'Site sombre',
            'latitude' => 48.0,
            'longitude' => -2.0,
            'timezone' => 'Europe/Paris',
            'is_default' => false,
        ]);
        $rig = EquipmentSetup::factory()->default()->create(['name' => 'Rig Principal']);
        CelestialObject::factory()->create([
            'catalog_number' => 'EDIT-1',
            'ra_deg' => 37.9546,
            'dec_deg' => 89.2641,
            'magnitude' => 4,
        ]);

        $this->actingAs($user)
            ->post(route('admin.nightscope.planner.store'), ['date' => '2026-08-03'])
            ->assertRedirect();

        $plan = ImagingPlan::query()->with('targets')->firstOrFail();
        $target = $plan->targets->firstOrFail();
        $originalStart = $target->planned_start_at->format('H:i');

        $this->actingAs($user)
            ->put(route('admin.nightscope.plans.targets.update', [$plan, $target]), [
                'planned_start' => '22:15',
                'planned_end' => '23:45',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $target->refresh();
        $this->assertSame('22:15', $target->planned_start_at->format('H:i'));
        $this->assertSame('23:45', $target->planned_end_at->format('H:i'));
        $this->assertNotSame($originalStart, '22:15');

        $this->actingAs($user)
            ->put(route('admin.nightscope.plans.update', $plan), [
                'date' => '2026-08-04',
                'observing_location_id' => $dark->id,
                'equipment_setup_id' => $rig->id,
                'notes' => 'Plan édité',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $plan->refresh();
        $this->assertSame('2026-08-04', $plan->date->format('Y-m-d'));
        $this->assertSame($dark->id, $plan->observing_location_id);
        $this->assertSame($rig->id, $plan->equipment_setup_id);
        $this->assertSame('Plan édité', $plan->notes);
        $this->assertSame('22:15', $plan->targets()->first()->planned_start_at->format('H:i'));
        $this->assertTrue($plan->targets()->first()->planned_start_at->isSameDay($plan->date));
    }
}
