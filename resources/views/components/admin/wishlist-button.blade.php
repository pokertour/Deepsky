@props(['target', 'favorite' => null])
<form method="POST" action="{{ route($favorite ? 'admin.nightscope.wishlist.destroy' : 'admin.nightscope.wishlist.update', $target) }}">
    @csrf
    @method($favorite ? 'DELETE' : 'PUT')
    <button type="submit" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-accent/30 bg-accent/10 px-3 py-2 text-xs text-accent transition hover:bg-accent/20" aria-label="{{ $favorite ? __('Retirer des favoris : :target', ['target' => $target->displayName()]) : __('Ajouter aux favoris : :target', ['target' => $target->displayName()]) }}">{{ $favorite ? '★ '.__('Favori') : '☆ '.__('Favori') }}</button>
</form>
