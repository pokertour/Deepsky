<?php

namespace App\Services\Security;

/**
 * Mots de passe à usage unique basés sur le temps (RFC 6238, HMAC-SHA1,
 * 6 chiffres, pas de 30 s), compatibles avec Aegis, Google Authenticator, 1Password…
 */
class TotpAuthenticator
{
    public const int DIGITS = 6;

    public const int PERIOD = 30;

    private const string BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $bytes = 20): string
    {
        return $this->base32Encode(random_bytes($bytes));
    }

    public function timestep(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    public function codeAt(string $secret, int $timestep): string
    {
        $hash = hash_hmac('sha1', pack('J', $timestep), $this->base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Retourne le pas de temps reconnu (pour bloquer sa réutilisation) ou null.
     * Une fenêtre d'un pas de part et d'autre absorbe la dérive d'horloge du téléphone.
     */
    public function verify(string $secret, string $code, int $window = 1, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $current = $this->timestep($timestamp);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->codeAt($secret, $current + $offset), $code)) {
                return $current + $offset;
            }
        }

        return null;
    }

    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer).':'.rawurlencode($account).'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $character) {
            $bits .= str_pad(decbin(ord($character)), 8, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, 5) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $encoded;
    }

    public function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[\s=-]+/', '', $secret));
        $bits = '';
        foreach (str_split($secret) as $character) {
            $position = strpos(self::BASE32_ALPHABET, $character);
            if ($position !== false) {
                $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
            }
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $decoded .= chr(bindec($byte));
            }
        }

        return $decoded;
    }
}
