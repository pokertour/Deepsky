<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginPageBackgroundTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_the_deepsky_identity(): void
    {
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee('DEEPSKY')
            ->assertSee('Administration console');
    }

    public function test_login_page_is_not_indexable(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('noindex,nofollow', escape: false);
    }
}
