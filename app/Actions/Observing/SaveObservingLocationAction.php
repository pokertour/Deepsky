<?php

namespace App\Actions\Observing;

use App\Models\ObservingLocation;
use Illuminate\Support\Facades\DB;

class SaveObservingLocationAction
{
    /** @param array<string, mixed> $data */
    public function execute(?ObservingLocation $location, array $data): ObservingLocation
    {
        return DB::transaction(function () use ($location, $data): ObservingLocation {
            $location ??= new ObservingLocation;
            $data['horizon_mask'] = filled($data['horizon_mask'] ?? null)
                ? json_decode($data['horizon_mask'], true, flags: JSON_THROW_ON_ERROR)
                : null;
            $location->fill($data);

            if ($location->is_default) {
                ObservingLocation::query()
                    ->when($location->exists, fn ($query) => $query->whereKeyNot($location->getKey()))
                    ->update(['is_default' => false]);
            }

            $location->save();

            return $location;
        });
    }
}
