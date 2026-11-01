<?php

namespace Tests\Unit;

use App\Support\TrustedHosts;
use PHPUnit\Framework\TestCase;

class TrustedHostsTest extends TestCase
{
    public function test_host_names_are_escaped_and_anchored(): void
    {
        $patterns = TrustedHosts::patterns(' deepsky.nexus , www.deepsky.nexus ,');

        $this->assertSame(['^deepsky\.nexus$', '^www\.deepsky\.nexus$'], $patterns);
        $this->assertTrue($this->isTrusted($patterns, 'deepsky.nexus'));
        $this->assertFalse($this->isTrusted($patterns, 'deepsky.nexus.attacker.example'));
        $this->assertFalse($this->isTrusted($patterns, 'deepskyXnexus'));
    }

    public function test_explicit_regular_expressions_are_kept(): void
    {
        $this->assertSame(['^(.+\.)?deepsky\.nexus$'], TrustedHosts::patterns('^(.+\.)?deepsky\.nexus$'));
        $this->assertSame([], TrustedHosts::patterns(null));
    }

    /**
     * Même construction que Symfony Request::setTrustedHosts().
     *
     * @param  list<string>  $patterns
     */
    private function isTrusted(array $patterns, string $host): bool
    {
        return collect($patterns)->contains(fn (string $pattern): bool => preg_match(sprintf('{%s}i', $pattern), $host) === 1);
    }
}
