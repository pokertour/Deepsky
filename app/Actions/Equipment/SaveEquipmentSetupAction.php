<?php

namespace App\Actions\Equipment;

use App\Models\EquipmentSetup;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SaveEquipmentSetupAction
{
    /** @param array<string, mixed> $data */
    public function execute(?EquipmentSetup $equipmentSetup, array $data): EquipmentSetup
    {
        return DB::transaction(function () use ($equipmentSetup, $data): EquipmentSetup {
            $equipmentSetup ??= new EquipmentSetup;
            $equipmentSetup->fill(Arr::except($data, ['setup_item_ids', 'roles']));

            if ($equipmentSetup->is_default) {
                EquipmentSetup::query()
                    ->when($equipmentSetup->exists, fn ($query) => $query->whereKeyNot($equipmentSetup->getKey()))
                    ->update(['is_default' => false]);
            }

            $equipmentSetup->save();
            $equipmentSetup->setupItems()->sync($this->pivotData($data));
            $equipmentSetup->touch();

            return $equipmentSetup->load('setupItems');
        });
    }

    /** @param array<string, mixed> $data @return array<int, array{role: string|null, position: int, enabled: bool, configuration: null}> */
    private function pivotData(array $data): array
    {
        $roles = $data['roles'] ?? [];
        $pivot = [];

        foreach (array_values($data['setup_item_ids'] ?? []) as $position => $setupItemId) {
            $pivot[(int) $setupItemId] = [
                'role' => filled($roles[$setupItemId] ?? null) ? $roles[$setupItemId] : null,
                'position' => $position,
                'enabled' => true,
                'configuration' => null,
            ];
        }

        return $pivot;
    }
}
