<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Equipment\SaveEquipmentSetupAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEquipmentSetupRequest;
use App\Http\Requests\Admin\UpdateEquipmentSetupRequest;
use App\Models\EquipmentSetup;
use App\Models\SetupItem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EquipmentSetupController extends Controller
{
    public function index(): View
    {
        $equipmentSetups = EquipmentSetup::query()
            ->with(['setupItems:id,name,brand,type'])
            ->latest('is_default')
            ->latest()
            ->paginate(12);

        return view('admin.equipment-setups.index', compact('equipmentSetups'));
    }

    public function create(): View
    {
        return view('admin.equipment-setups.create', $this->formData());
    }

    public function store(StoreEquipmentSetupRequest $request, SaveEquipmentSetupAction $save): RedirectResponse
    {
        $save->execute(null, $request->validated());

        return redirect()->route('admin.equipment-setups.index')->with('success', __('Rig ajouté.'));
    }

    public function edit(EquipmentSetup $equipmentSetup): View
    {
        $equipmentSetup->load('setupItems');

        return view('admin.equipment-setups.edit', ['equipmentSetup' => $equipmentSetup, ...$this->formData()]);
    }

    public function update(UpdateEquipmentSetupRequest $request, EquipmentSetup $equipmentSetup, SaveEquipmentSetupAction $save): RedirectResponse
    {
        $save->execute($equipmentSetup, $request->validated());

        return redirect()->route('admin.equipment-setups.index')->with('success', __('Rig mis à jour.'));
    }

    public function destroy(EquipmentSetup $equipmentSetup): RedirectResponse
    {
        if ($equipmentSetup->astrophotos()->exists()) {
            return back()->with('error', __('Ce rig est utilisé par des astrophotos et ne peut pas être supprimé.'));
        }

        $equipmentSetup->delete();

        return back()->with('success', __('Rig supprimé.'));
    }

    /** @return array{setupItems: Collection<int, SetupItem>} */
    private function formData(): array
    {
        return [
            'setupItems' => SetupItem::query()->orderBy('type')->orderBy('brand')->orderBy('name')->get(),
        ];
    }
}
