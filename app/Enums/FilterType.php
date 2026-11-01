<?php

namespace App\Enums;

enum FilterType: string
{
    case UvIrCut = 'uv_ir_cut';
    case Broadband = 'broadband';
    case DualBand = 'dual_band';
    case Narrowband = 'narrowband';
    case Luminance = 'luminance';
    case Color = 'color';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::UvIrCut => 'UV/IR Cut',
            self::Broadband => 'Broadband',
            self::DualBand => 'Dual-band',
            self::Narrowband => __('Bande étroite'),
            self::Luminance => 'Luminance',
            self::Color => __('Couleur'),
            self::Other => __('Autre'),
        };
    }

    public function productHint(): string
    {
        return match ($this) {
            self::UvIrCut => 'ex. UV/IR cut',
            self::DualBand => 'ex. L-Ultimate',
            self::Narrowband => 'ex. Ha / OIII / SII',
            self::Broadband, self::Luminance => 'ex. L / RGB',
            default => $this->label(),
        };
    }

    public function isEmissionSelective(): bool
    {
        return match ($this) {
            self::DualBand, self::Narrowband => true,
            default => false,
        };
    }

    public function isBroadbandFamily(): bool
    {
        return match ($this) {
            self::UvIrCut, self::Broadband, self::Luminance, self::Color => true,
            default => false,
        };
    }
}
