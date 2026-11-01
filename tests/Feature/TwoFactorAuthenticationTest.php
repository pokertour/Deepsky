<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\TotpAuthenticator;
use App\Services\Security\TwoFactorAuthentication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const string SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    protected function setUp(): void
    {
        parent::setUp();

        config(['admin.emails' => ['owner@example.com']]);
    }

    private function admin(bool $withTwoFactor = true): User
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('correct-password')]);

        if ($withTwoFactor) {
            app(TwoFactorAuthentication::class)->enable($user, self::SECRET);
        }

        return $user;
    }

    private function currentCode(): string
    {
        $totp = new TotpAuthenticator;

        return $totp->codeAt(self::SECRET, $totp->timestep());
    }

    private function submitPassword(): TestResponse
    {
        return $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'correct-password', 'remember' => '1']);
    }

    public function test_password_alone_does_not_open_a_session_when_two_factor_is_enabled(): void
    {
        $this->admin();

        $this->submitPassword()->assertRedirect(route('admin.two-factor.challenge'));

        $this->assertGuest();
        $this->get(route('admin.two-factor.challenge'))->assertOk()->assertSee('Double authentification');
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_valid_code_completes_the_login_and_cannot_be_replayed(): void
    {
        $user = $this->admin();
        $code = $this->currentCode();

        $this->submitPassword();
        $this->post(route('admin.two-factor.challenge.store'), ['code' => $code])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post(route('admin.logout'));
        $this->submitPassword();
        $this->post(route('admin.two-factor.challenge.store'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_invalid_codes_are_rejected_and_eventually_reset_the_login(): void
    {
        $this->admin();
        $this->submitPassword();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.two-factor.challenge.store'), ['code' => '000000'])->assertSessionHasErrors('code');
        }

        $this->post(route('admin.two-factor.challenge.store'), ['code' => $this->currentCode()])
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();
        $this->get(route('admin.two-factor.challenge'))->assertRedirect(route('admin.login'));
    }

    public function test_recovery_codes_work_once(): void
    {
        $user = $this->admin();
        $codes = app(TwoFactorAuthentication::class)->regenerateRecoveryCodes($user);

        $this->submitPassword();
        $this->post(route('admin.two-factor.challenge.store'), ['recovery_code' => strtoupper($codes[0])])
            ->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('warning');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(7, app(TwoFactorAuthentication::class)->remainingRecoveryCodes($user->fresh()));

        $this->post(route('admin.logout'));
        $this->submitPassword();
        $this->post(route('admin.two-factor.challenge.store'), ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_pending_verification_expires(): void
    {
        $this->admin();
        $this->submitPassword();

        $this->travel(6)->minutes();

        $this->post(route('admin.two-factor.challenge.store'), ['code' => $this->currentCode()])
            ->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_admin_enables_two_factor_from_the_security_page(): void
    {
        $user = $this->admin(withTwoFactor: false);

        $this->actingAs($user)->get(route('admin.security.edit'))->assertOk()->assertSee('otpauth://totp/Deepsky', false);
        $secret = session('admin.two_factor_pending_secret');
        $totp = new TotpAuthenticator;

        $this->post(route('admin.security.two-factor.enable'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('admin.security.two-factor.enable'), ['code' => $totp->codeAt($secret, $totp->timestep())])
            ->assertRedirect(route('admin.security.edit'))
            ->assertSessionHas('recoveryCodes', fn (array $codes): bool => count($codes) === TwoFactorAuthentication::RECOVERY_CODE_COUNT);

        $user->refresh();
        $this->assertTrue($user->hasTwoFactorEnabled());
        $this->assertNotSame($secret, $user->getRawOriginal('app_authentication_secret'));
    }

    public function test_disabling_two_factor_requires_the_current_password(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->delete(route('admin.security.two-factor.disable'), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->delete(route('admin.security.two-factor.disable'), ['password' => 'correct-password'])->assertRedirect(route('admin.security.edit'));
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_login_without_two_factor_still_works_directly(): void
    {
        $user = $this->admin(withTwoFactor: false);

        $this->submitPassword()->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
