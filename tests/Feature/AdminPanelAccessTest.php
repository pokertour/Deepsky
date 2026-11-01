<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    public function test_whitelisted_email_has_admin_access(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $this->assertTrue(User::factory()->make(['email' => 'owner@example.com'])->isAdmin());
    }

    public function test_non_whitelisted_email_has_no_admin_access(): void
    {
        config(['admin.emails' => ['owner@example.com']]);
        $this->assertFalse(User::factory()->make(['email' => 'intruder@example.com'])->isAdmin());
    }

    public function test_empty_whitelist_denies_access(): void
    {
        config(['admin.emails' => []]);
        $this->assertFalse(User::factory()->make(['email' => 'owner@example.com'])->isAdmin());
    }
}
