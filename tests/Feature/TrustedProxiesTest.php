<?php

namespace Tests\Feature;

use App\Support\TrustedProxies;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

class TrustedProxiesTest extends TestCase
{
    public function test_environment_value_is_parsed_into_what_trust_proxies_expects(): void
    {
        $this->assertSame('*', TrustedProxies::from(' * '));
        $this->assertSame(['172.18.0.0/16', '10.0.0.1'], TrustedProxies::from('172.18.0.0/16, 10.0.0.1'));
        $this->assertNull(TrustedProxies::from(''));
        $this->assertNull(TrustedProxies::from(null));
    }

    public function test_a_wildcard_restores_the_visitor_ip_and_https_behind_the_proxy(): void
    {
        $seen = null;
        TrustProxies::at(TrustedProxies::from('*'));
        $request = Request::create('http://deepsky.example/', 'GET', server: [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ]);

        (new TrustProxies)->handle($request, function (Request $request) use (&$seen) {
            $seen = [$request->ip(), $request->isSecure()];

            return response('ok');
        });

        $this->assertSame(['203.0.113.9', true], $seen);
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();

        parent::tearDown();
    }
}
