@props(['compact' => false])

<section id="newsletter" aria-labelledby="newsletter-title" data-has-feedback="{{ session('newsletter_status') || $errors->has('email') ? 'true' : 'false' }}" class="w-full scroll-mt-8">
    @if ($compact)
        <h2 id="newsletter-title" class="text-center font-mono text-xs uppercase tracking-widest text-accent">{{ __('Une nouvelle photo, un email') }}</h2>
        <p id="newsletter-help" class="mt-3 text-center text-sm leading-relaxed text-text-main/70">{{ __('Les prochaines images du ciel profond, directement dans votre boîte mail.') }}</p>
    @else
        <p class="font-mono text-xs uppercase tracking-widest text-accent">{{ __('Une nouvelle photo, un email') }}</p>
        <h2 id="newsletter-title" class="mt-2 text-xl font-semibold text-text-bold">{{ __('Gardez un œil sur le ciel profond') }}</h2>
        <p id="newsletter-help" class="mt-3 text-sm leading-relaxed text-text-main/75">{{ __('Recevez un lien vers chaque nouvelle photo, son récit de capture et ses données techniques. Confirmez votre inscription par email pour commencer.') }}</p>
    @endif

    @session('newsletter_status')
        <p role="status" tabindex="-1" class="mt-4 rounded-lg border border-accent/30 bg-accent/10 p-3 text-sm text-accent">{{ $value }}</p>
    @endsession
    @error('email')
        <p id="newsletter-error" role="alert" tabindex="-1" class="mt-4 rounded-lg border border-red-400/30 bg-red-400/10 p-3 text-sm text-red-300">{{ $message }}</p>
    @enderror

    @inject('captcha', 'App\Services\CaptchaService')
    <form id="newsletter-form" method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-5">
        @csrf
        <div class="hidden" aria-hidden="true">
            <label for="newsletter-website">{{ __('Ne pas remplir ce champ') }}</label>
            <input type="text" id="newsletter-website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <label for="newsletter-email" class="{{ $compact ? 'sr-only' : 'mb-2 block text-sm font-medium text-text-bold' }}">{{ __('Votre adresse email') }}</label>
        <div class="flex flex-col gap-3 sm:flex-row">
            <input id="newsletter-email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" inputmode="email" autocapitalize="none" spellcheck="false"
                aria-describedby="newsletter-help newsletter-privacy{{ $errors->has('email') ? ' newsletter-error' : '' }}" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                placeholder="vous@exemple.fr" class="min-h-12 min-w-0 flex-1 rounded-lg border border-border {{ $compact ? 'bg-card-bg' : 'bg-bg' }} px-4 py-3 text-sm text-text-main placeholder:text-text-main/50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent">
            <button type="submit" class="{{ $captcha->provider() === App\Enums\CaptchaProvider::Hcaptcha ? 'h-captcha ' : '' }}min-h-12 shrink-0 rounded-lg px-5 py-3 text-sm transition focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent {{ $compact ? 'border border-accent/50 font-medium text-accent hover:bg-accent/10' : 'bg-accent font-semibold text-slate-950 hover:bg-sky-300' }}"
                @if ($captcha->provider() === App\Enums\CaptchaProvider::Hcaptcha)
                    data-sitekey="{{ $captcha->siteKey() }}" data-size="invisible" data-callback="onNewsletterCaptchaVerified"
                @endif>
                {{ $compact ? __('S’inscrire') : __('Recevoir les prochaines photos') }}
            </button>
        </div>
        @if ($captcha->provider() === App\Enums\CaptchaProvider::Turnstile)
            <div class="cf-turnstile mt-3" data-sitekey="{{ $captcha->siteKey() }}" data-action="newsletter" data-size="flexible" data-appearance="interaction-only"></div>
        @endif
    </form>
    <p id="newsletter-privacy" class="mt-3 text-xs leading-relaxed text-text-main/60 {{ $compact ? 'text-center' : '' }}">{{ $compact ? __('Un email par publication. Inscription à confirmer par email, désinscription à tout moment.') : __('Votre adresse sert uniquement à recevoir les nouvelles publications. Désinscription via chaque email. Pensez à vérifier vos spams pour la confirmation.') }}</p>
    @if ($captcha->provider() !== App\Enums\CaptchaProvider::None)
        @push('scripts')
            <script src="{{ $captcha->provider()->scriptUrl() }}?render=explicit&amp;onload=onNewsletterCaptchaLoaded" data-navigate-once defer></script>
        @endpush
    @endif
</section>
