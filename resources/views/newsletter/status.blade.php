@extends('layouts.app')

@section('title', __('Newsletter — Deepsky'))
@section('robots', 'noindex, follow')
@section('content')
    <div class="md:col-span-full flex justify-center py-16">
        <div class="max-w-md w-full bg-card-bg border border-border rounded-lg p-8 text-center">
            <p class="text-accent text-3xl mb-4" aria-hidden="true">{{ isset($unsubscribeToken) ? '?' : ($success ? '✓' : '✗') }}</p>
            <h2 class="text-accent uppercase text-[0.8rem] tracking-[0.2rem] font-bold mb-4">{{ __('Newsletter') }}</h2>
            <p class="text-[0.9rem] font-mono text-text-main leading-relaxed">{{ $message }}</p>
            @isset($unsubscribeToken)
                <form method="POST" action="{{ route('newsletter.unsubscribe.store', $unsubscribeToken) }}" class="mt-8">
                    @csrf
                    <button type="submit"
                        class="w-full rounded-lg bg-accent px-5 py-3 text-[0.75rem] font-mono font-bold uppercase tracking-[0.15rem] text-bg transition-opacity hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent">
                        {{ __('Me désinscrire') }}
                    </button>
                </form>
            @endisset
            <a wire:navigate href="{{ route('welcome') }}"
                class="inline-block mt-8 border border-accent text-accent rounded-lg px-5 py-2 text-[0.7rem] font-mono uppercase tracking-[0.15rem] hover:bg-accent hover:text-bg transition-colors">
                {{ __('Retour à la galerie') }}
            </a>
        </div>
    </div>
@endsection
