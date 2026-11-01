<?php

namespace App\Actions\Astronomy;

use App\Models\CelestialObject;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SaveCelestialObjectAction
{
    /** @param array<string, mixed> $data */
    public function execute(?CelestialObject $celestialObject, array $data): CelestialObject
    {
        $celestialObject ??= new CelestialObject;
        $celestialObject->fill([
            ...Arr::except($data, 'alternative_names_text'),
            'alternative_names' => $this->alternativeNames($data['alternative_names_text'] ?? null),
        ]);
        $celestialObject->save();

        return $celestialObject;
    }

    /** @return list<string>|null */
    private function alternativeNames(?string $names): ?array
    {
        if (blank($names)) {
            return null;
        }

        $values = Str::of($names)
            ->split('/[,\r\n]+/')
            ->map(fn (string $name): string => Str::squish($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $values === [] ? null : $values;
    }
}
