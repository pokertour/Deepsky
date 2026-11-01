<?php

namespace Tests\Feature;

use App\Enums\TaskRunStatus;
use App\Models\TaskRun;
use App\Models\User;
use App\Notifications\NightScopeDigestNotification;
use App\Services\Notifications\TaskFailureNotifier;
use App\Settings\NotificationSettings;
use App\Support\RunnableCommands;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskFailureAlertTest extends TestCase
{
    use RefreshDatabase, WithConsoleEvents;

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Artisan::command('nightscope:test-failure', fn (): int => 1);
    }

    public function test_failed_command_sends_an_alert_on_ready_channels(): void
    {
        $this->fakeNotificationSettings();

        $this->artisan('nightscope:test-failure')->assertExitCode(1);

        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'échec : nightscope:test-failure')
            && str_contains($request['text'], 'Code de sortie 1')
            && str_contains($request['text'], route('admin.tasks.index', ['status' => 'failed'])));
    }

    public function test_repeated_failures_alert_once_per_hour(): void
    {
        $this->fakeNotificationSettings();

        $this->artisan('nightscope:test-failure');
        $this->artisan('nightscope:test-failure');

        Http::assertSentCount(1);
        $this->assertSame(2, TaskRun::where('status', TaskRunStatus::Failed)->count());
    }

    public function test_alert_can_be_disabled(): void
    {
        $this->fakeNotificationSettings(['alert_task_failures' => false]);

        $this->artisan('nightscope:test-failure');

        Http::assertNothingSent();
    }

    public function test_failed_alert_notification_does_not_trigger_another_alert(): void
    {
        $this->fakeNotificationSettings();
        $run = TaskRun::factory()->job(NightScopeDigestNotification::class)->failed()->create();

        $this->assertFalse(app(TaskFailureNotifier::class)->notify($run));
        Http::assertNothingSent();
    }

    public function test_settings_page_saves_the_task_failure_alert_option(): void
    {
        $this->fakeNotificationSettings();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Échec d’une tâche ou d’un traitement');

        $this->actingAs($admin)
            ->put(route('admin.settings.notifications.update'), [
                'telegram_bot_token' => '',
                'telegram_chat_id' => '',
                'gotify_url' => '',
                'gotify_token' => '',
                'ntfy_url' => '',
                'ntfy_topic' => '',
                'mail_recipient' => '',
                'alert_task_failures' => '0',
                'daily_min_score' => 65,
                'daily_time' => '16:00',
                'weekly_time' => '09:00',
            ])
            ->assertSessionHas('success');

        $this->assertFalse(app(NotificationSettings::class)->alert_task_failures);
    }

    public function test_dashboard_lists_recent_failures_only_when_present(): void
    {
        Storage::fake('s3');
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Échecs récents');

        TaskRun::factory()->job()->failed('Imagick : délai dépassé')->create(['subject' => 'Photo #3 · M 42', 'started_at' => now()->subHour()]);
        TaskRun::factory()->failed()->create(['name' => 'r2:warm-stats', 'started_at' => now()->subDays(2)]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Échecs récents')
            ->assertSee('Photo #3 · M 42')
            ->assertDontSee('Statistiques de stockage R2');
    }

    public function test_digest_schedule_follows_the_configured_time(): void
    {
        $this->fakeNotificationSettings(['daily_time' => '18:30', 'weekly_time' => '09:00']);
        $timezone = config('nightscope.notifications.timezone', 'Europe/Paris');
        $settings = app(NotificationSettings::class);

        $this->travelTo(now($timezone)->next('Monday')->setTime(18, 30));
        $this->assertTrue($settings->isDailyDigestDue());
        $this->assertFalse($settings->isWeeklyReportDue());

        $this->travelTo(now($timezone)->setTime(9, 0));
        $this->assertTrue($settings->isWeeklyReportDue());
        $this->assertFalse($settings->isDailyDigestDue());
    }

    public function test_manual_digest_launch_forces_sending(): void
    {
        $this->assertSame(['--force' => true], RunnableCommands::parameters('nightscope:notify-daily'));
        $this->assertSame(['--force' => true], RunnableCommands::parameters('nightscope:notify-weekly'));
    }

    /** @param  array<string, mixed>  $overrides */
    private function fakeNotificationSettings(array $overrides = []): void
    {
        NotificationSettings::fake([
            'telegram_enabled' => true,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id' => '-1001234567890',
            'gotify_enabled' => false,
            'gotify_url' => null,
            'gotify_token' => null,
            'ntfy_enabled' => false,
            'ntfy_url' => 'https://ntfy.sh',
            'ntfy_topic' => null,
            'mail_enabled' => false,
            'mail_recipient' => null,
            'alert_daily_conditions' => false,
            'alert_best_targets' => false,
            'alert_weekly_report' => false,
            'alert_task_failures' => true,
            'daily_min_score' => 65,
            'daily_time' => '16:00',
            'weekly_time' => '09:00',
            ...$overrides,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com']);
    }
}
