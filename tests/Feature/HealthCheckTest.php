<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_checks_the_database_and_the_cache(): void
    {
        $this->get('/up')->assertOk();

        $this->assertNotNull(cache('health:last-check'));
    }

    public function test_health_endpoint_fails_when_the_database_is_unreachable(): void
    {
        config(['app.debug' => false]);
        DB::shouldReceive('select')->once()->with('select 1')->andThrow(new RuntimeException('Connection refused'));

        $this->get('/up')->assertInternalServerError();
    }
}
