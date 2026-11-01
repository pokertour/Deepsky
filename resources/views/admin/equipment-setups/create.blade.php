<x-admin.layout title="{{ __('Nouveau rig') }}" eyebrow="{{ __('Configuration matérielle') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.equipment-setups.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60">{{ __('Annuler') }}</a></x-slot:actions>
    <form action="{{ route('admin.equipment-setups.store') }}" method="POST">
        @csrf
        <x-admin.equipment-setup-form :setup-items="$setupItems" />
    </form>
</x-admin.layout>
