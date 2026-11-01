<x-admin.auth-shell title="{{ __('Connexion') }}">
    @php($input = 'w-full rounded-xl border border-white/10 bg-[#05080f]/70 px-4 py-3 text-text-bold outline-none transition placeholder:text-text-main/30 focus:border-accent focus:ring-4 focus:ring-accent/15')
    <form action="{{ route('admin.login.store') }}" method="POST"
        x-data="{ submitting: false, revealed: false, capsLock: false }"
        @submit.prevent="if (! submitting) { submitting = true; warp().then(() => $el.submit()) }"
        @pageshow.window="submitting = false"
        class="rounded-3xl border border-white/10 bg-[#0d1117]/75 p-6 shadow-[0_30px_80px_-20px_rgba(0,0,0,.8)] backdrop-blur-xl sm:p-8">@csrf
        <div class="mb-6">
            <p class="text-xl font-semibold text-text-bold">{{ __('Bon retour') }}</p>
            <p class="mt-1 text-sm text-text-main/60">{{ __('Identifie-toi pour accéder au centre de contrôle.') }}</p>
        </div>
        <div class="grid gap-5">
            <label class="grid gap-2 text-sm">
                <span class="font-medium text-text-bold">{{ __('Adresse email') }}</span>
                <input name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="255" class="{{ $input }}" placeholder="admin@deepsky.fr" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                @error('email')<span id="email-error" class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label class="grid gap-2 text-sm">
                <span class="font-medium text-text-bold">{{ __('Mot de passe') }}</span>
                <span class="relative">
                    <input name="password" :type="revealed ? 'text' : 'password'" type="password" required autocomplete="current-password" class="{{ $input }} pr-12"
                        @input="shootingStar()" @keydown="capsLock = $event.getModifierState?.('CapsLock') ?? false" @keyup="capsLock = $event.getModifierState?.('CapsLock') ?? false">
                    <button type="button" @click="revealed = ! revealed" :aria-pressed="revealed" :aria-label="revealed ? @js(__('Masquer le mot de passe')) : @js(__('Afficher le mot de passe'))" aria-label="{{ __('Afficher le mot de passe') }}"
                        class="absolute inset-y-0 right-0 grid w-12 place-items-center rounded-r-xl text-text-main/50 transition hover:text-accent focus-visible:text-accent focus-visible:outline-none">
                        <svg x-show="! revealed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M2.04 12.32a1 1 0 0 1 0-.64C3.42 7.51 7.36 4.5 12 4.5c4.64 0 8.57 3 9.96 7.18a1 1 0 0 1 0 .64C20.58 16.49 16.64 19.5 12 19.5c-4.64 0-8.57-3-9.96-7.18Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                        <svg x-cloak x-show="revealed" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="size-5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.22A10.48 10.48 0 0 0 2.04 12c1.29 4.34 5.31 7.5 10.07 7.5.99 0 1.95-.14 2.86-.4M6.23 6.23A10.45 10.45 0 0 1 12 4.5c4.76 0 8.77 3.16 10.07 7.5a10.52 10.52 0 0 1-4.29 5.77M6.23 6.23 3 3m3.23 3.23 3.65 3.65m7.89 7.89L21 21m-3.23-3.23-3.65-3.65m0 0a3 3 0 1 0-4.24-4.24m4.24 4.24L9.88 9.88"/></svg>
                    </button>
                </span>
                <span x-cloak x-show="capsLock" class="text-xs text-amber-200">{{ __('⇪ Verrouillage majuscules activé') }}</span>
            </label>
            <label class="flex items-center gap-2 text-sm text-text-main/65"><input name="remember" type="checkbox" value="1" @checked(old('remember')) class="size-4 rounded border-border bg-bg accent-accent"> {{ __('Garder ma session ouverte') }}</label>
            <button type="submit" :disabled="submitting" :aria-busy="submitting" class="group relative flex items-center justify-center gap-2 overflow-hidden rounded-xl bg-accent px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-300 focus:ring-4 focus:ring-accent/25 disabled:cursor-wait disabled:opacity-80">
                <span class="pointer-events-none absolute inset-y-0 -left-1/2 w-1/2 -skew-x-12 bg-white/30 opacity-0 transition-all duration-700 group-hover:left-full group-hover:opacity-100"></span>
                <svg x-cloak x-show="submitting" aria-hidden="true" class="size-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                </svg>
                <span aria-live="polite" x-text="submitting ? @js(__('Mise en orbite…')) : @js(__('Ouvrir le centre de contrôle'))">{{ __('Ouvrir le centre de contrôle') }}</span>
            </button>
        </div>
    </form>
</x-admin.auth-shell>
