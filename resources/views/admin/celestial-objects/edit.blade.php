<x-admin.layout title="{{ __('Modifier l’objet') }}" eyebrow="{{ $celestialObject->designation() }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.celestial-objects.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60">{{ __('Retour') }}</a></x-slot:actions>
    <form action="{{ route('admin.celestial-objects.update', $celestialObject) }}" method="POST">
        @csrf @method('PUT')
        <x-admin.celestial-object-form :celestial-object="$celestialObject" />
    </form>
</x-admin.layout>
