<x-admin.layout title="{{ __('Nouvel objet céleste') }}" eyebrow="{{ __('Catalogue') }}">
    <x-slot:actions><a wire:navigate href="{{ route('admin.celestial-objects.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60">{{ __('Annuler') }}</a></x-slot:actions>
    <form action="{{ route('admin.celestial-objects.store') }}" method="POST">
        @csrf
        <x-admin.celestial-object-form />
    </form>
</x-admin.layout>
