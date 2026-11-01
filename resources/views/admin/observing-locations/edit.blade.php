<x-admin.layout title="{{ __('Modifier le lieu') }}" eyebrow="{{ __('Lieu d’observation') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.observing-locations.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60">{{ __('Retour') }}</a></x-slot:actions>
    <form action="{{ route('admin.observing-locations.update', $observingLocation) }}" method="POST">
        @csrf @method('PUT')
        <x-admin.observing-location-form :observing-location="$observingLocation" :timezones="$timezones" />
    </form>
</x-admin.layout>
