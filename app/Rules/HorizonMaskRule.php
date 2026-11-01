<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;
use stdClass;

class HorizonMaskRule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('Le masque doit être un objet JSON azimut → hauteur.'));

            return;
        }

        try {
            $mask = json_decode($value, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $fail(__('Le masque doit être un objet JSON valide.'));

            return;
        }

        if (! $mask instanceof stdClass || count(get_object_vars($mask)) > 720) {
            $fail(__('Le masque doit être un objet JSON contenant au maximum 720 points.'));

            return;
        }

        $bearings = [];
        foreach ($mask as $azimuth => $height) {
            if (! is_numeric($azimuth) || ! is_finite((float) $azimuth)
                || (float) $azimuth < 0 || (float) $azimuth >= 360
                || (! is_int($height) && ! is_float($height)) || ! is_finite((float) $height)
                || $height < 0 || $height > 90) {
                $fail(__('Chaque azimut doit être compris entre 0 et 360° (exclu), et chaque hauteur entre 0 et 90°.'));

                return;
            }

            $normalized = (string) (float) $azimuth;
            if (isset($bearings[$normalized])) {
                $fail(__('Chaque azimut doit être unique.'));

                return;
            }
            $bearings[$normalized] = true;
        }
    }
}
