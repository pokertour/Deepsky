<x-admin.layout title="{{ __('Modifier l’astrophoto') }}" eyebrow="{{ $photo->target ?: __('Vault céleste') }}">
    <x-slot:actions>
        <a wire:navigate href="{{ route('astrophoto.show', $photo) }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60 hover:text-text-bold">{{ __('Aperçu') }}</a>
        <form action="{{ route('admin.astrophotos.retry-conversions', $photo) }}" method="POST">
            @csrf
            <button data-retry-conversions class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60 hover:text-text-bold" @disabled($photo->conversion_status === App\Enums\ConversionStatus::Converting)>{{ __('Relancer les conversions') }}</button>
        </form>
        <form action="{{ route('admin.astrophotos.solve', $photo) }}" method="POST">
            @csrf
            <button class="rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-xs font-medium text-accent transition hover:bg-accent/20">{{ __('✦ Relancer l’astrométrie') }}</button>
        </form>
        <form action="{{ route('admin.astrophotos.distances', $photo) }}" method="POST">
            @csrf
            <button class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60 hover:text-text-bold" title="{{ __('Interroge SIMBAD pour l’objet principal et les objets identifiés dans le champ') }}">{{ __('⟳ Synchroniser les distances') }}</button>
        </form>
        <a wire:navigate href="{{ route('admin.astrophotos.index') }}" class="rounded-xl border border-border px-3 py-2 text-xs text-text-main/60 hover:text-text-bold">{{ __('Retour') }}</a>
    </x-slot:actions>
    <form action="{{ route('admin.astrophotos.update', $photo) }}" method="POST" enctype="multipart/form-data" data-astrophoto-upload>
        @csrf
        @method('PUT')
        @include('admin.astrophotos.form')
    </form>
</x-admin.layout>
