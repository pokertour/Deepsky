<?php

namespace Tests\Feature;

use App\Enums\TaskRunStatus;
use App\Enums\TaskRunType;
use App\Jobs\EnrichCelestialDistanceJob;
use App\Jobs\RunScheduledCommandJob;
use App\Models\CelestialObject;
use App\Models\TaskRun;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class TaskHistoryTest extends TestCase
{
    use RefreshDatabase, WithConsoleEvents;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    public function test_tracked_command_run_is_recorded_with_its_options(): void
    {
        $this->artisan('newsletter:purge-unconfirmed --days=3')->assertSuccessful();

        $run = TaskRun::sole();
        $this->assertSame(TaskRunType::Command, $run->type);
        $this->assertSame('newsletter:purge-unconfirmed', $run->name);
        $this->assertSame(TaskRunStatus::Succeeded, $run->status);
        $this->assertSame('--days=3', $run->subject);
        $this->assertNotNull($run->finished_at);
        $this->assertNotNull($run->duration_ms);
    }

    public function test_failing_command_is_recorded_as_failed(): void
    {
        Artisan::command('nightscope:test-failure', fn (): int => 2);

        $this->artisan('nightscope:test-failure')->assertExitCode(2);

        $run = TaskRun::sole();
        $this->assertSame(TaskRunStatus::Failed, $run->status);
        $this->assertSame('Code de sortie 2', $run->message);
    }

    public function test_untracked_commands_are_ignored(): void
    {
        Artisan::command('inspire:test', fn (): int => 0);

        $this->artisan('inspire:test')->assertSuccessful();

        $this->assertSame(0, TaskRun::count());
    }

    public function test_successful_job_is_recorded_with_its_subject(): void
    {
        $object = CelestialObject::factory()->create(['catalog' => 'M', 'catalog_number' => '42', 'name' => 'Orion', 'distance_ly' => 1344.0]);

        EnrichCelestialDistanceJob::dispatch($object);

        $run = TaskRun::sole();
        $this->assertSame(TaskRunType::Job, $run->type);
        $this->assertSame(EnrichCelestialDistanceJob::class, $run->name);
        $this->assertSame(TaskRunStatus::Succeeded, $run->status);
        $this->assertSame('M 42 — Orion', $run->subject);
        $this->assertSame('Distance SIMBAD', $run->label());
    }

    public function test_failing_job_is_recorded_with_the_exception(): void
    {
        try {
            FailingTaskHistoryJob::dispatch();
        } catch (RuntimeException) {
        }

        $run = TaskRun::sole();
        $this->assertSame(TaskRunStatus::Failed, $run->status);
        $this->assertSame('RuntimeException : SIMBAD indisponible', $run->message);
    }

    public function test_released_job_is_recorded_as_rescheduled(): void
    {
        ReleasingTaskHistoryJob::dispatch();

        $this->assertSame(TaskRunStatus::Released, TaskRun::sole()->status);
    }

    public function test_admin_sees_history_summary_and_can_filter_by_status(): void
    {
        TaskRun::factory()->create(['name' => 'nightscope:warm-weather', 'started_at' => now()->subHours(2)]);
        TaskRun::factory()->failed('Code de sortie 1')->create(['name' => 'r2:warm-stats', 'started_at' => now()->subHour()]);
        TaskRun::factory()->job()->failed('Imagick : délai dépassé')->create(['subject' => 'Photo #7 · Rosette']);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Préchauffage météo')
            ->assertSee('Statistiques de stockage R2')
            ->assertSee('Conversion d’images')
            ->assertSee('Photo #7 · Rosette')
            ->assertSee('Imagick : délai dépassé');

        $this->actingAs($admin)
            ->get(route('admin.tasks.index', ['status' => TaskRunStatus::Failed->value, 'type' => TaskRunType::Job->value]))
            ->assertOk()
            ->assertSee('Photo #7 · Rosette')
            ->assertDontSee('Code de sortie 1');
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.tasks.index', ['status' => 'unknown']))
            ->assertSessionHasErrors('status');
    }

    public function test_history_is_restricted_to_admins(): void
    {
        $this->get(route('admin.tasks.index'))->assertRedirect(route('admin.login'));

        $this->actingAs(User::factory()->create(['email' => 'visitor@example.com']))
            ->get(route('admin.tasks.index'))
            ->assertForbidden();
    }

    public function test_page_lists_every_runnable_command_with_a_launch_button(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.tasks.index'))
            ->assertOk()
            ->assertSee('Import du catalogue OpenNGC')
            ->assertSee('Jamais exécutée depuis l’activation de l’historique')
            ->assertSee('value="nightscope:import-catalog"', false)
            ->assertSee('▶ Lancer');
    }

    public function test_admin_can_launch_a_command_through_the_queue(): void
    {
        Queue::fake();

        $this->actingAs($this->admin())
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.store'), ['command' => 'nightscope:warm-weather'])
            ->assertRedirect(route('admin.tasks.index'))
            ->assertSessionHas('success');

        Queue::assertPushed(RunScheduledCommandJob::class, fn (RunScheduledCommandJob $job): bool => $job->command === 'nightscope:warm-weather');
    }

    public function test_launch_is_refused_while_the_same_command_is_queued_or_running(): void
    {
        Queue::fake();
        $admin = $this->admin();
        TaskRun::factory()->create(['name' => 'r2:warm-stats', 'status' => TaskRunStatus::Running, 'started_at' => now()->subMinutes(2)]);

        $this->actingAs($admin)->post(route('admin.tasks.store'), ['command' => 'r2:warm-stats'])->assertSessionHas('error');
        $this->actingAs($admin)->post(route('admin.tasks.store'), ['command' => 'nightscope:warm-weather'])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.tasks.store'), ['command' => 'nightscope:warm-weather'])->assertSessionHas('error');

        Queue::assertPushed(RunScheduledCommandJob::class, 1);
    }

    public function test_only_whitelisted_commands_can_be_launched(): void
    {
        Queue::fake();

        $this->actingAs($this->admin())
            ->from(route('admin.tasks.index'))
            ->post(route('admin.tasks.store'), ['command' => 'migrate:fresh'])
            ->assertRedirect(route('admin.tasks.index'))
            ->assertSessionHasErrors('command')
            ->assertSessionHasInput('command', 'migrate:fresh');

        $this->actingAs(User::factory()->create(['email' => 'visitor@example.com']))
            ->post(route('admin.tasks.store'), ['command' => 'nightscope:warm-weather'])
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_manual_launch_is_recorded_once_as_a_manual_command_run(): void
    {
        RunScheduledCommandJob::dispatch('newsletter:purge-unconfirmed');

        $run = TaskRun::sole();
        $this->assertSame(TaskRunType::Command, $run->type);
        $this->assertSame('newsletter:purge-unconfirmed', $run->name);
        $this->assertSame(TaskRunStatus::Succeeded, $run->status);
        $this->assertSame('Lancé manuellement', $run->subject);
    }

    public function test_old_runs_are_pruned(): void
    {
        $old = TaskRun::factory()->create(['started_at' => now()->subDays(31)]);
        $recent = TaskRun::factory()->create(['started_at' => now()->subDays(5)]);

        $this->artisan('model:prune', ['--model' => [TaskRun::class]])->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }
}

class FailingTaskHistoryJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        throw new RuntimeException('SIMBAD indisponible');
    }
}

class ReleasingTaskHistoryJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $this->release(30);
    }
}
