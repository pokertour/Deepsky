<?php

namespace App\Enums;

enum LoginEventType: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case TwoFactorFailed = 'two_factor_failed';
    case LockedOut = 'locked_out';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => __('Connexion'),
            self::Failed => __('Mot de passe refusé'),
            self::TwoFactorFailed => __('Code 2FA refusé'),
            self::LockedOut => __('Verrouillage'),
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Succeeded => 'border-green-400/20 bg-green-400/10 text-green-300',
            self::Failed, self::TwoFactorFailed => 'border-amber-400/20 bg-amber-400/10 text-amber-300',
            self::LockedOut => 'border-red-400/25 bg-red-400/10 text-red-300',
        };
    }
}
