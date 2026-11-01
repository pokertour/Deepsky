<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_admin_uses_the_first_configured_admin_email(): void
    {
        config(['admin.emails' => ['owner@example.com', 'second@example.com']]);

        $this->seed(DatabaseSeeder::class);

        $this->assertTrue(User::sole()->is(User::where('email', 'owner@example.com')->first()));
    }

    public function test_seeder_falls_back_to_an_example_address(): void
    {
        config(['admin.emails' => []]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame('admin@example.com', User::sole()->email);
    }
}
