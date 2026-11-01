<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SetupItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSetupItemRequest;
use App\Http\Requests\Admin\UpdateSetupItemRequest;
use App\Models\SetupItem;
use App\Support\Equipment\SetupItemSpecificationSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SetupItemController extends Controller
{
    public function index(Request $request): View
    {
        $items = SetupItem::query()
            ->select('setup_items.*')
            ->withPhotoUsesCount()
            ->withCount('equipmentSetups')
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')))
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where(function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where('name', 'like', $search)->orWhere('brand', 'like', $search);
            }))
            ->orderBy('type')
            ->orderBy('brand')
            ->paginate(15)
            ->withQueryString();

        return view('admin.equipment.index', [
            'items' => $items,
            'typeOptions' => SetupItemType::options(),
        ]);
    }

    public function create(): View
    {
        return view('admin.equipment.create', $this->formData());
    }

    public function store(StoreSetupItemRequest $request): RedirectResponse
    {
        SetupItem::create($request->validated());

        return redirect()->route('admin.equipment.index')->with('success', __('Équipement ajouté.'));
    }

    public function edit(SetupItem $equipment): View
    {
        return view('admin.equipment.edit', ['item' => $equipment, ...$this->formData()]);
    }

    public function update(UpdateSetupItemRequest $request, SetupItem $equipment): RedirectResponse
    {
        $equipment->update($request->validated());
        $equipment->equipmentSetups()->touch();

        return redirect()->route('admin.equipment.index')->with('success', __('Équipement mis à jour.'));
    }

    public function destroy(SetupItem $equipment): RedirectResponse
    {
        if ($equipment->photosAsCamera()->exists()
            || $equipment->photosAsMount()->exists()
            || $equipment->photosAsTube()->exists()
            || $equipment->equipmentSetups()->exists()) {
            return back()->with('error', __('Cet équipement est encore utilisé par une photo ou un rig.'));
        }

        $equipment->delete();

        return back()->with('success', __('Équipement supprimé.'));
    }

    /** @return array{typeOptions: array<string, string>, specificationFields: array<string, array<string, mixed>>} */
    private function formData(): array
    {
        return [
            'typeOptions' => SetupItemType::options(),
            'specificationFields' => SetupItemSpecificationSchema::fields(),
        ];
    }
}
