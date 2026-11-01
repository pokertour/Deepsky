<x-admin.auth-shell title="{{ __('Vérification') }}">
    @php($input = 'w-full rounded-xl border border-white/10 bg-[#05080f]/70 px-4 py-3 text-text-bold outline-none transition placeholder:text-text-main/30 focus:border-accent focus:ring-4 focus:ring-accent/15')
    <form action="{{ route('admin.two-factor.challenge.store') }}" method="POST"
        x-data="{ submitting: false, useRecovery: {{ $errors->has('recovery_code') ? 'true' : 'false' }} }"
        @submit.prevent="if (! submitting) { submitting = true; warp().then(() => $el.submit()) }"
        @pageshow.window="submitting = false"
        class="rounded-3xl border border-white/10 bg-[#0d1117]/75 p-6 shadow-[0_30px_80px_-20px_rgba(0,0,0,.8)] backdrop-blur-xl sm:p-8">@csrf
        <div class="mb-6">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.25em] text-accent">{{ __('Étape 2 sur 2') }}</p>
            <p class="mt-2 text-xl font-semibold text-text-bold">{{ __('Double authentification') }}</p>
            <p class="mt-1 text-sm text-text-main/60" x-text="useRecovery ? @js(__('Saisis l’un de tes codes de secours.')) : @js(__('Saisis le code à 6 chiffres affiché par ton application.'))">{{ __('Saisis le code à 6 chiffres affiché par ton application.') }}</p>
        </div>
        <div class="grid gap-5">
            <label x-show="! useRecovery" class="grid gap-2 text-sm">
                <span class="sr-only">{{ __('Code de vérification') }}</span>
                <input name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" autofocus placeholder="••• •••" :disabled="useRecovery"
                    @input="shootingStar(); if ($event.target.value.replace(/\D/g, '').length === 6) $el.form.requestSubmit()"
                    class="{{ $input }} text-center font-[var(--font-mono)] text-2xl tracking-[.5em]">
                @error('code')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <label x-cloak x-show="useRecovery" class="grid gap-2 text-sm">
                <span class="font-medium text-text-bold">{{ __('Code de secours') }}</span>
                <input name="recovery_code" autocomplete="off" maxlength="32" placeholder="{{ __('abcde-12345') }}" :disabled="! useRecovery" class="{{ $input }} font-[var(--font-mono)] tracking-wider">
                @error('recovery_code')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
            </label>
            <button type="submit" :disabled="submitting" class="rounded-xl bg-accent px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-300 focus:ring-4 focus:ring-accent/25 disabled:cursor-wait disabled:opacity-80" x-text="submitting ? @js(__('Vérification…')) : @js(__('Valider'))">{{ __('Valider') }}</button>
            <button type="button" @click="useRecovery = ! useRecovery; $nextTick(() => $el.closest('form').querySelector(useRecovery ? '[name=recovery_code]' : '[name=code]').focus())"
                class="text-xs text-text-main/55 transition hover:text-accent" x-text="useRecovery ? @js(__('Utiliser plutôt mon application')) : @js(__('Téléphone perdu ? Utiliser un code de secours'))">{{ __('Téléphone perdu ? Utiliser un code de secours') }}</button>
        </div>
    </form>
</x-admin.auth-shell>
