<?php

namespace App\Casts;

use App\Data\Equipment\SetupItemSpecs;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/** @implements CastsAttributes<SetupItemSpecs, SetupItemSpecs|array<string, mixed>|null> */
class SetupItemSpecsCast implements CastsAttributes
{
    /** @throws JsonException */
    public function get(Model $model, string $key, mixed $value, array $attributes): SetupItemSpecs
    {
        return SetupItemSpecs::from($value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR));
    }

    /** @throws JsonException */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $values = $value instanceof SetupItemSpecs ? $value->toArray() : $value;

        return $values === [] ? null : json_encode($values, JSON_THROW_ON_ERROR);
    }
}
