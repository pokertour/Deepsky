<?php

namespace App\Support\Equipment;

use App\Enums\SetupItemType;

final class SetupItemSpecificationSchema
{
    /** @return array<string, array{label: string, type: string, step?: string, suffix?: string, options?: array<string, string>, types: list<string>, rules: list<string>}> */
    public static function fields(): array
    {
        return [
            'sensor_width_mm' => self::number(__('Largeur du capteur'), 'mm', ['camera', 'guide_camera'], ['numeric', 'gt:0'], '0.01'),
            'sensor_height_mm' => self::number(__('Hauteur du capteur'), 'mm', ['camera', 'guide_camera'], ['numeric', 'gt:0'], '0.01'),
            'resolution_x' => self::number(__('Résolution X'), 'px', ['camera', 'guide_camera'], ['integer', 'min:1'], '1'),
            'resolution_y' => self::number(__('Résolution Y'), 'px', ['camera', 'guide_camera'], ['integer', 'min:1'], '1'),
            'pixel_size_um' => self::number(__('Taille des pixels'), 'µm', ['camera', 'guide_camera'], ['numeric', 'gt:0'], '0.01'),
            'cooled' => self::boolean(__('Caméra refroidie'), ['camera', 'guide_camera']),
            'preferred_gain' => self::number(__('Gain préféré'), '', ['camera'], ['integer', 'min:0'], '1'),
            'typical_sub_exposure_seconds' => self::number(__('Sous-pose typique'), 's', ['camera'], ['integer', 'min:1', 'max:3600'], '1'),
            'aperture_mm' => self::number(__('Ouverture'), 'mm', ['optical_tube', 'guide_scope'], ['numeric', 'gt:0'], '0.1'),
            'focal_length_mm' => self::number(__('Longueur focale'), 'mm', ['optical_tube', 'guide_scope'], ['numeric', 'gt:0'], '0.1'),
            'focal_ratio' => self::number(__('Rapport focal'), 'f/', ['optical_tube', 'guide_scope'], ['numeric', 'gt:0'], '0.01'),
            'equatorial' => self::boolean(__('Monture équatoriale'), ['mount']),
            'goto' => self::boolean('GoTo', ['mount']),
            'meridian_flip' => self::boolean(__('Retournement au méridien'), ['mount']),
            'factor' => self::number(__('Facteur optique'), '×', ['reducer', 'corrector'], ['numeric', 'gt:0', 'max:5'], '0.01'),
            'filter_type' => [
                'label' => __('Type de filtre'),
                'type' => 'select',
                'types' => ['filter'],
                'options' => [
                    'uv_ir_cut' => 'UV/IR Cut',
                    'broadband' => 'Broadband',
                    'dual_band' => 'Dual Band',
                    'narrowband' => __('Bande étroite'),
                    'luminance' => 'Luminance',
                    'color' => __('Couleur'),
                    'other' => __('Autre'),
                ],
                'rules' => ['string', 'max:50'],
            ],
            'bandwidth_nm' => self::number(__('Largeur de bande'), 'nm', ['filter'], ['numeric', 'gt:0'], '0.1'),
            'central_wavelength_nm' => self::number(__('Longueur d’onde centrale'), 'nm', ['filter'], ['numeric', 'gt:0'], '0.1'),
        ];
    }

    /** @return array<string, list<string>> */
    public static function rulesFor(?string $type): array
    {
        if (SetupItemType::tryFrom((string) $type) === null) {
            return [];
        }

        $rules = [];

        foreach (self::fields() as $key => $field) {
            if (in_array($type, $field['types'], true)) {
                $rules["specs.$key"] = ['nullable', ...$field['rules']];
            }
        }

        return $rules;
    }

    /** @return list<string> */
    public static function keysFor(?string $type): array
    {
        return collect(self::fields())
            ->filter(fn (array $field): bool => in_array($type, $field['types'], true))
            ->keys()
            ->all();
    }

    /** @param list<string> $types @param list<string> $rules */
    private static function number(string $label, string $suffix, array $types, array $rules, string $step): array
    {
        return compact('label', 'suffix', 'types', 'rules', 'step') + ['type' => 'number'];
    }

    /** @param list<string> $types */
    private static function boolean(string $label, array $types): array
    {
        return compact('label', 'types') + ['type' => 'boolean', 'rules' => ['boolean']];
    }
}
