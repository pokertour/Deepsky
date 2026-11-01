@props(['class' => ''])
<nav aria-label="{{ __('Langue') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 rounded-full border border-border p-0.5 font-mono text-[10px] uppercase tracking-[.12em] '.$class]) }}>
    @foreach (App\Support\Locales::all() as $code => $name)
        @if ($code === app()->getLocale())
            <span aria-current="true" title="{{ $name }}" class="rounded-full bg-accent/15 px-2 py-1 font-semibold text-accent">{{ $code }}</span>
        @else
            <a href="{{ App\Support\Locales::switchUrl($code) }}" hreflang="{{ $code }}" lang="{{ $code }}" title="{{ $name }}" class="rounded-full px-2 py-1 text-text-main/60 transition hover:text-accent">{{ $code }}</a>
        @endif
    @endforeach
</nav>
