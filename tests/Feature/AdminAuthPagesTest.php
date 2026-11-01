<?php

namespace Tests\Feature;

use App\Http\Requests\Admin\LoginRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AdminAuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_custom_login_page_renders(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('Bon retour');
    }

    public function test_admin_can_log_out(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->actingAs($user)->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('correct-password')]);

        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');
    }

    public function test_account_is_locked_after_repeated_failures_even_with_the_right_password(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('correct-password')]);

        for ($attempt = 0; $attempt < LoginRequest::MAX_ATTEMPTS_PER_ACCOUNT; $attempt++) {
            RateLimiter::hit('admin-login:'.hash('sha256', 'owner@example.com'), LoginRequest::LOCKOUT_SECONDS);
        }

        $this->post(route('admin.login.store'), ['email' => 'Owner@Example.com', 'password' => 'correct-password'])
            ->assertSessionHasErrors(['email' => 'Trop de tentatives de connexion pour ce compte. Réessayez dans 15 min.']);

        $this->assertGuest();
    }

    public function test_successful_login_resets_the_failure_counter(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        User::factory()->create(['email' => 'owner@example.com', 'password' => Hash::make('correct-password')]);

        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'wrong-password']);
        $this->post(route('admin.login.store'), ['email' => 'owner@example.com', 'password' => 'correct-password'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertSame(0, RateLimiter::attempts('admin-login:'.hash('sha256', 'owner@example.com')));
    }
}
