<?php

namespace Tests\Unit;

use App\Services\Security\TotpAuthenticator;
use PHPUnit\Framework\TestCase;

class TotpAuthenticatorTest extends TestCase
{
    private const string RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    public function test_codes_match_the_rfc_6238_reference_vectors(): void
    {
        $totp = new TotpAuthenticator;

        $this->assertSame('287082', $totp->codeAt(self::RFC_SECRET, $totp->timestep(59)));
        $this->assertSame('081804', $totp->codeAt(self::RFC_SECRET, $totp->timestep(1111111109)));
        $this->assertSame('005924', $totp->codeAt(self::RFC_SECRET, $totp->timestep(1234567890)));
    }

    public function test_verification_tolerates_one_step_of_clock_drift_only(): void
    {
        $totp = new TotpAuthenticator;
        $now = 1_700_000_000;
        $step = $totp->timestep($now);

        $this->assertSame($step - 1, $totp->verify(self::RFC_SECRET, $totp->codeAt(self::RFC_SECRET, $step - 1), timestamp: $now));
        $this->assertSame($step, $totp->verify(self::RFC_SECRET, '  '.$totp->codeAt(self::RFC_SECRET, $step), timestamp: $now));
        $this->assertNull($totp->verify(self::RFC_SECRET, $totp->codeAt(self::RFC_SECRET, $step + 3), timestamp: $now));
        $this->assertNull($totp->verify(self::RFC_SECRET, 'abc123', timestamp: $now));
    }

    public function test_generated_secrets_round_trip_through_base32(): void
    {
        $totp = new TotpAuthenticator;
        $secret = $totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        $this->assertSame($secret, $totp->base32Encode($totp->base32Decode($secret)));
    }

    public function test_provisioning_uri_targets_authenticator_apps(): void
    {
        $uri = (new TotpAuthenticator)->provisioningUri(self::RFC_SECRET, 'astro@example.com', 'Deepsky');

        $this->assertStringStartsWith('otpauth://totp/Deepsky:astro%40example.com?secret='.self::RFC_SECRET, $uri);
        $this->assertStringContainsString('issuer=Deepsky', $uri);
    }
}
