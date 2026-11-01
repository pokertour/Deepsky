@if ($paginator->hasPages())
    @php
        $linkClasses = 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-border px-3 text-text-main transition-all duration-300 hover:border-accent hover:text-accent';
        $disabledClasses = 'inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-border px-3 text-text-main/30 cursor-not-allowed';
        $pageClasses = 'hidden min-h-10 min-w-10 items-center justify-center rounded-lg border border-border px-3 text-text-main transition-all duration-300 hover:border-accent hover:text-accent sm:inline-flex';
    @endphp
    <nav role="navigation" aria-label="{{ __('Pagination') }}" class="flex flex-wrap items-center justify-center gap-2 font-mono text-[0.8rem]">
        @if ($paginator->onFirstPage())
            <span class="{{ $disabledClasses }}" aria-disabled="true"><span aria-hidden="true">‹</span><span class="ml-1.5 hidden sm:inline">{{ __('Précédent') }}</span></span>
        @else
            <a wire:navigate href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $linkClasses }}" aria-label="{{ __('Page précédente') }}"><span aria-hidden="true">‹</span><span class="ml-1.5 hidden sm:inline">{{ __('Précédent') }}</span></a>
        @endif

        <span class="px-2 text-text-main/60 sm:hidden">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="hidden px-1 text-text-main/50 sm:inline">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span aria-current="page" class="hidden min-h-10 min-w-10 items-center justify-center rounded-lg border border-accent bg-accent px-3 font-bold text-bg shadow-[0_0_10px_rgba(88,166,255,0.3)] sm:inline-flex">{{ $page }}</span>
                    @else
                        <a wire:navigate href="{{ $url }}" class="{{ $pageClasses }}" aria-label="{{ __('Page :page', ['page' => $page]) }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a wire:navigate href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $linkClasses }}" aria-label="{{ __('Page suivante') }}"><span class="mr-1.5 hidden sm:inline">{{ __('Suivant') }}</span><span aria-hidden="true">›</span></a>
        @else
            <span class="{{ $disabledClasses }}" aria-disabled="true"><span class="mr-1.5 hidden sm:inline">{{ __('Suivant') }}</span><span aria-hidden="true">›</span></span>
        @endif
    </nav>
@endif
