<?php

namespace App\Enums;

enum ConversionStatus: string
{
    case Missing = 'missing';
    case Queued = 'queued';
    case Converting = 'converting';
    case Ready = 'ready';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Missing => __('Image manquante'),
            self::Queued => __('Conversions en attente'),
            self::Converting => __('Conversion en cours'),
            self::Ready => __('Images prêtes'),
            self::Failed => __('Échec des conversions'),
        };
    }
}
