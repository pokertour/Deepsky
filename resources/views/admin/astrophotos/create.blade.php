<x-admin.layout title="{{ __('Nouvelle astrophoto') }}" eyebrow="{{ __('Acquisition') }}">
    <x-slot:actions>
        <a wire:navigate href="{{ route('admin.astrophotos.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60 hover:text-text-bold">{{ __('Annuler') }}</a>
    </x-slot:actions>
    <form action="{{ route('admin.astrophotos.store') }}" method="POST" enctype="multipart/form-data" data-astrophoto-upload>
        @csrf
        @include('admin.astrophotos.form', ['photo' => null])
    </form>
</x-admin.layout>
