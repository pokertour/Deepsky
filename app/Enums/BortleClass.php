<?php

namespace App\Enums;

enum BortleClass: int
{
    case Excellent = 1;
    case TrulyDark = 2;
    case Rural = 3;
    case RuralSuburban = 4;
    case Suburban = 5;
    case BrightSuburban = 6;
    case SuburbanUrban = 7;
    case City = 8;
    case InnerCity = 9;

    public function label(): string
    {
        return match ($this) {
            self::Excellent => '1 — Excellent',
            self::TrulyDark => __('2 — Vraiment sombre'),
            self::Rural => '3 — Rural',
            self::RuralSuburban => __('4 — Rural / périurbain'),
            self::Suburban => '5 — Banlieue',
            self::BrightSuburban => __('6 — Banlieue claire'),
            self::SuburbanUrban => __('7 — Périurbain / ville'),
            self::City => '8 — Ville',
            self::InnerCity => '9 — Centre-ville',
        };
    }

    public function isLightPolluted(): bool
    {
        $threshold = (int) config('nightscope.filters.light_polluted_bortle', 5);

        return $this->value >= $threshold;
    }

    /** @return list<int> */
    public static function values(): array
    {
        return array_map(fn (self $case): int => $case->value, self::cases());
    }
}
