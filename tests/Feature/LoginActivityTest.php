<?php

namespace Tests\Feature;

use App\Enums\LoginEventType;
use App\Models\LoginEvent;
use App\Models\User;
use App\Services\Security\LoginActivityRecorder;
use App\Services\Security\TwoFactorAuthentication;
use App\Settings\NotificationSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginActivityTest extends TestCase
{
    use RefreshDatabase;

    private const string LAPTOP = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Safari/605.1.15';

    private const string PHONE = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Mobile Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        NotificationSettings::fake([
            'telegram_enabled' => true,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id' => '-1001234567890',
            'alert_security_events' => true,
            'alert_every_login' => false,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('correct-password')]);
    }

    private function login(string $userAgent, string $password = 'correct-password'): void
    {
        $this->withHeader('User-Agent', $userAgent)
            ->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => $password]);
        $this->post(route('admin.logout'));
    }

    public function test_successful_and_failed_logins_are_journaled(): void
    {
        $this->admin();

        $this->login(self::LAPTOP, 'wrong-password');
        $this->login(self::LAPTOP);

        $this->assertSame([LoginEventType::Failed, LoginEventType::Succeeded], LoginEvent::query()->oldest('id')->pluck('type')->all());
        $this->assertDatabaseHas('login_events', ['type' => LoginEventType::Failed->value, 'email' => 'owner@example.com', 'ip_address' => '127.0.0.1']);
    }

    public function test_new_devices_trigger_an_alert_but_browser_updates_do_not(): void
    {
        $this->admin();

        $this->login(self::LAPTOP);
        Http::assertSentCount(1);
        $this->login(str_replace('Version/17.5', 'Version/18.1', self::LAPTOP));
        Http::assertSentCount(1);

        $this->login(self::PHONE);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'nouvel appareil') && str_contains($request['text'], 'Chrome · Android'));
    }

    public function test_every_login_is_alerted_when_enabled(): void
    {
        NotificationSettings::fake([
            'telegram_enabled' => true,
            'telegram_bot_token' => 'bot-token-123',
            'telegram_chat_id' => '-1',
            'alert_security_events' => true,
            'alert_every_login' => true,
        ]);
        $this->admin();

        $this->login(self::LAPTOP);
        $this->login(self::LAPTOP);

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'appareil déjà connu'));
    }

    public function test_lockouts_and_refused_two_factor_codes_are_journaled_and_alerted(): void
    {
        $user = $this->admin();
        app(TwoFactorAuthentication::class)->enable($user, 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'correct-password']);
        $this->post(route('admin.two-factor.challenge.store'), ['code' => '000000']);
        $this->assertDatabaseHas('login_events', ['type' => LoginEventType::TwoFactorFailed->value, 'user_id' => $user->id]);

        for ($attempt = 0; $attempt < 10; $attempt++) {
            RateLimiter::hit('admin-login:'.hash('sha256', 'owner@example.com'), 900);
        }
        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'correct-password']);

        $this->assertDatabaseHas('login_events', ['type' => LoginEventType::LockedOut->value]);
        Http::assertSent(fn (Request $request): bool => str_contains($request['text'], 'compte admin verrouillé'));
    }

    public function test_alerts_can_be_disabled(): void
    {
        NotificationSettings::fake(['telegram_enabled' => true, 'telegram_bot_token' => 'bot-token-123', 'telegram_chat_id' => '-1', 'alert_security_events' => false, 'alert_every_login' => true]);
        $this->admin();

        $this->login(self::LAPTOP);
        $this->login(self::PHONE);

        Http::assertNothingSent();
        $this->assertSame(2, LoginEvent::query()->where('type', LoginEventType::Succeeded)->count());
    }

    public function test_security_page_lists_recent_activity_and_marks_the_current_device(): void
    {
        $user = $this->admin();
        LoginEvent::factory()->for($user)->create(['user_agent' => self::PHONE, 'device_hash' => LoginActivityRecorder::deviceHash(self::PHONE), 'ip_address' => '203.0.113.7']);

        $this->actingAs($user)->withHeader('User-Agent', self::PHONE)
            ->get(route('admin.security.edit'))
            ->assertOk()
            ->assertSee('Connexions récentes')
            ->assertSee('203.0.113.7')
            ->assertSee('Chrome · Android')
            ->assertSee('cet appareil');
    }

    public function test_other_sessions_can_be_closed_with_the_current_password(): void
    {
        $user = $this->admin();
        $previousHash = $user->password;

        $this->actingAs($user)->post(route('admin.security.sessions.destroy'), ['password' => 'wrong'])
            ->assertSessionHasErrorsIn('sessions', 'password');
        $this->assertSame($previousHash, $user->fresh()->password);

        $this->post(route('admin.security.sessions.destroy'), ['password' => 'correct-password'])
            ->assertRedirect(route('admin.security.edit'))
            ->assertSessionHas('success');
        $this->assertNotSame($previousHash, $user->fresh()->password);
        $this->get(route('admin.security.edit'))->assertOk();
    }

    public function test_old_events_are_pruned(): void
    {
        LoginEvent::factory()->create(['created_at' => now()->subDays(LoginEvent::RETENTION_DAYS + 1)]);
        LoginEvent::factory()->create();

        $this->artisan('model:prune', ['--model' => [LoginEvent::class]])->assertSuccessful();

        $this->assertSame(1, LoginEvent::query()->count());
    }
}
