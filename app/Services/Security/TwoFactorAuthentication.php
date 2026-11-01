<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Str;

/**
 * Double authentification des comptes admin : secret TOTP chiffré en base,
 * codes de secours à usage unique stockés hachés, codes TOTP non rejouables.
 */
class TwoFactorAuthentication
{
    public const int RECOVERY_CODE_COUNT = 8;

    public function __construct(private TotpAuthenticator $totp) {}

    /**
     * @return list<string> codes de secours en clair, à n'afficher qu'une fois
     */
    public function enable(User $user, string $secret): array
    {
        $user->forceFill(['app_authentication_secret' => $secret])->save();

        return $this->regenerateRecoveryCodes($user);
    }

    public function disable(User $user): void
    {
        $user->forceFill([
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ])->save();
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $codes = collect(range(1, self::RECOVERY_CODE_COUNT))
            ->map(fn (): string => Str::lower(Str::random(5).'-'.Str::random(5)))
            ->all();

        $user->forceFill([
            'app_authentication_recovery_codes' => array_map($this->hashRecoveryCode(...), $codes),
        ])->save();

        return $codes;
    }

    public function verifyTotp(User $user, string $code): bool
    {
        $timestep = $this->totp->verify((string) $user->app_authentication_secret, $code);

        // Un code déjà accepté (par exemple intercepté) ne doit pas servir une seconde fois.
        return $timestep !== null
            && cache()->add("two-factor-used:{$user->id}:{$timestep}", true, TotpAuthenticator::PERIOD * 4);
    }

    public function useRecoveryCode(User $user, string $code): bool
    {
        $hash = $this->hashRecoveryCode($code);
        $remaining = collect($user->app_authentication_recovery_codes ?? []);

        if (! $remaining->contains(fn (string $stored): bool => hash_equals($stored, $hash))) {
            return false;
        }

        $user->forceFill([
            'app_authentication_recovery_codes' => $remaining->reject(fn (string $stored): bool => hash_equals($stored, $hash))->values()->all(),
        ])->save();

        return true;
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->app_authentication_recovery_codes ?? []);
    }

    private function hashRecoveryCode(string $code): string
    {
        return hash_hmac('sha256', Str::lower(trim($code)), (string) config('app.key'));
    }
}
