<?php

namespace App\Enums;

enum SetupItemType: string
{
    case Camera = 'camera';
    case Mount = 'mount';
    case OpticalTube = 'optical_tube';
    case FilterWheel = 'filter_wheel';
    case Filter = 'filter';
    case Reducer = 'reducer';
    case Corrector = 'corrector';
    case GuideCamera = 'guide_camera';
    case GuideScope = 'guide_scope';
    case Oag = 'oag';
    case Focuser = 'focuser';
    case Rotator = 'rotator';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Camera => __('Caméra'),
            self::Mount => __('Monture'),
            self::OpticalTube => __('Tube optique'),
            self::FilterWheel => __('Roue à filtres'),
            self::Filter => __('Filtre'),
            self::Reducer => __('Réducteur'),
            self::Corrector => __('Correcteur'),
            self::GuideCamera => __('Caméra de guidage'),
            self::GuideScope => __('Lunette de guidage'),
            self::Oag => __('Diviseur optique'),
            self::Focuser => __('Moteur de mise au point'),
            self::Rotator => __('Rotateur'),
            self::Other => __('Autre'),
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
