<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SetUserPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sets_a_hashed_password_for_an_existing_user(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('user:set-password', [
            'email' => 'owner@example.com',
            '--password' => 'NewSecret123!',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertTrue(Hash::check('NewSecret123!', $user->password));
        $this->assertNotSame('NewSecret123!', $user->password);
    }

    public function test_it_fails_when_the_user_does_not_exist(): void
    {
        $this->artisan('user:set-password', [
            'email' => 'missing@example.com',
            '--password' => 'whatever',
        ])->assertFailed();
    }
}
