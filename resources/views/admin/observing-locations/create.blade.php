<x-admin.layout title="{{ __('Nouveau lieu') }}" eyebrow="{{ __('Lieu d’observation') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.observing-locations.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60">{{ __('Annuler') }}</a></x-slot:actions>
    <form action="{{ route('admin.observing-locations.store') }}" method="POST">
        @csrf
        <x-admin.observing-location-form :timezones="$timezones" />
    </form>
</x-admin.layout>
