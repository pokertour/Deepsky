<?php

namespace App\Support;

use Illuminate\Support\Number;

/**
 * Traduit une distance en « âge de la lumière » : la lumière d'un objet situé à
 * N années-lumière est partie il y a N années. Chaque durée est mise en regard de
 * ce qui se passait alors sur Terre.
 */
class LightTravel
{
    /**
     * Seuils en années, du plus récent au plus ancien.
     *
     * @var list<array{0: float, 1: string}>
     */
    public const array EPOCHS = [
        [150, 'du vivant de nos arrière-grands-parents'],
        [1_000, 'au Moyen Âge'],
        [2_200, 'au temps de l’Empire romain'],
        [5_000, 'quand on bâtissait les pyramides d’Égypte'],
        [12_000, 'à la fin de la dernière glaciation'],
        [45_000, 'quand nos ancêtres peignaient les premières grottes'],
        [300_000, 'à l’apparition d’Homo sapiens'],
        [3_000_000, 'au temps des premiers outils de pierre'],
        [8_000_000, 'quand la lignée humaine se séparait de celle des chimpanzés'],
        [66_000_000, 'quand les mammifères prenaient la place des dinosaures'],
        [250_000_000, 'au temps des dinosaures'],
        [540_000_000, 'avant les dinosaures, quand la vie explosait dans les océans'],
        [4_600_000_000, 'avant même l’apparition d’une vie complexe sur Terre'],
    ];

    /**
     * Repères de la frise « machine à remonter le temps » : [années, événement].
     *
     * @var list<array{0: float, 1: string}>
     */
    public const array MILESTONES = [
        [123, 'Premier vol motorisé des frères Wright'],
        [534, 'Christophe Colomb traverse l’Atlantique'],
        [2_000, 'Apogée de l’Empire romain'],
        [4_500, 'Construction des pyramides de Gizeh'],
        [12_000, 'Fin de la dernière glaciation'],
        [36_000, 'Peintures de la grotte Chauvet'],
        [300_000, 'Apparition d’Homo sapiens'],
        [2_600_000, 'Premiers outils de pierre taillée'],
        [7_000_000, 'Les lignées humaine et chimpanzé se séparent'],
        [66_000_000, 'Disparition des dinosaures'],
        [230_000_000, 'Premiers dinosaures'],
        [540_000_000, 'Explosion de la vie dans les océans'],
        [4_600_000_000, 'Naissance du Système solaire'],
    ];

    public static function epoch(float $years): string
    {
        foreach (self::EPOCHS as [$limit, $label]) {
            if ($years <= $limit) {
                return __($label);
            }
        }

        return __('avant la naissance du Système solaire');
    }

    public static function duration(float $years): string
    {
        $locale = app()->getLocale();

        return match (true) {
            $years >= 1_000_000_000 => trans_choice(':count milliard d’années|:count milliards d’années', $years >= 2_000_000_000 ? 2 : 1, [
                'count' => Number::format($years / 1_000_000_000, maxPrecision: 1, locale: $locale),
            ]),
            $years >= 1_000_000 => trans_choice(':count million d’années|:count millions d’années', $years >= 2_000_000 ? 2 : 1, [
                'count' => Number::format($years / 1_000_000, maxPrecision: 1, locale: $locale),
            ]),
            $years >= 2 => __(':count ans', ['count' => Number::format(round($years, $years >= 100 ? -1 : 0), locale: $locale)]),
            default => __('quelques mois'),
        };
    }

    /**
     * @return array{duration: string, epoch: string}|null
     */
    public static function describe(?float $lightYears): ?array
    {
        if ($lightYears === null || $lightYears <= 0) {
            return null;
        }

        return ['duration' => self::duration($lightYears), 'epoch' => self::epoch($lightYears)];
    }
}
