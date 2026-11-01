<x-admin.layout title="{{ __('Modifier le rig') }}" eyebrow="{{ __('Configuration matérielle') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.equipment-setups.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60">{{ __('Retour') }}</a></x-slot:actions>
    <form action="{{ route('admin.equipment-setups.update', $equipmentSetup) }}" method="POST">
        @csrf @method('PUT')
        <x-admin.equipment-setup-form :equipment-setup="$equipmentSetup" :setup-items="$setupItems" />
    </form>
</x-admin.layout>
