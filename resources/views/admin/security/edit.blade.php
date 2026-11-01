<x-admin.layout title="{{ __('Sécurité') }}" eyebrow="{{ __('Compte administrateur') }}">
    @php($input = 'w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none transition placeholder:text-text-main/30 focus:border-accent focus:ring-4 focus:ring-accent/10')

    @session('recoveryCodes')
        <section class="mb-6 rounded-2xl border border-amber-400/30 bg-amber-400/[.06] p-5 sm:p-6" x-data="{ copied: false }">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-amber-200">{{ __('À conserver maintenant') }}</p>
            <h2 class="mt-1 font-semibold text-text-bold">{{ __('Codes de secours') }}</h2>
            <p class="mt-2 max-w-2xl text-sm text-text-main/60">{{ __('Chaque code permet une connexion si tu perds ton téléphone, une seule fois. Ils ne seront plus jamais affichés : range-les dans ton gestionnaire de mots de passe.') }}</p>
            <ol class="mt-5 grid max-w-xl grid-cols-2 gap-2 font-[var(--font-mono)] text-sm text-text-bold" x-ref="codes">
                @foreach ($value as $code)
                    <li class="rounded-lg border border-border bg-bg px-3 py-2 text-center tracking-wider">{{ $code }}</li>
                @endforeach
            </ol>
            <button type="button" class="mt-4 rounded-lg border border-border px-4 py-2 text-xs text-text-main/80 transition hover:border-accent/40 hover:text-accent"
                @click="navigator.clipboard.writeText(@js(implode("\n", $value))).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                x-text="copied ? @js(__('Copié ✓')) : @js(__('Copier les codes'))">{{ __('Copier les codes') }}</button>
        </section>
    @endsession

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('01 · Connexion') }}</p>
                    <h2 class="mt-1 font-semibold text-text-bold">{{ __('Double authentification') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm text-text-main/55">{{ __('Après le mot de passe, un code à 6 chiffres généré par ton téléphone est demandé. Un mot de passe volé ne suffit plus à ouvrir l’administration.') }}</p>
                </div>
                @if ($user->hasTwoFactorEnabled())
                    <span class="inline-flex items-center gap-2 rounded-full border border-green-400/30 bg-green-400/10 px-3 py-1 text-xs font-medium text-green-300"><span class="size-1.5 rounded-full bg-green-300"></span>{{ __('Active') }}</span>
                @else
                    <span class="inline-flex items-center gap-2 rounded-full border border-amber-400/30 bg-amber-400/10 px-3 py-1 text-xs font-medium text-amber-200"><span class="size-1.5 rounded-full bg-amber-200"></span>{{ __('Inactive') }}</span>
                @endif
            </div>

            @if ($user->hasTwoFactorEnabled())
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <form action="{{ route('admin.security.recovery-codes') }}" method="POST" class="grid content-start gap-3 rounded-xl border border-border bg-bg/50 p-4">@csrf
                        <h3 class="text-sm font-semibold text-text-bold">{{ __('Codes de secours') }}</h3>
                        <p class="text-sm text-text-main/55">{{ __('Il reste') }} <strong class="text-text-bold">{{ $remainingRecoveryCodes }}</strong> {{ __('code:value inutilisé:value2. En générer de nouveaux invalide les anciens.', ['value' => $remainingRecoveryCodes > 1 ? 's' : '', 'value2' => $remainingRecoveryCodes > 1 ? 's' : '']) }}</p>
                        <label class="grid gap-2 text-sm font-medium text-text-bold">{{ __('Mot de passe actuel') }}<input name="password" type="password" required autocomplete="current-password" class="{{ $input }}"></label>
                        <button class="rounded-xl border border-accent/40 px-4 py-2.5 text-sm font-medium text-accent transition hover:bg-accent/10">{{ __('Générer de nouveaux codes') }}</button>
                    </form>
                    <form action="{{ route('admin.security.two-factor.disable') }}" method="POST" class="grid content-start gap-3 rounded-xl border border-red-400/20 bg-red-400/[.04] p-4" data-confirm="{{ __('Le mot de passe seul suffira de nouveau pour se connecter.') }}" data-confirm-title="{{ __('Désactiver la 2FA ?') }}" data-confirm-label="{{ __('Désactiver') }}" data-confirm-tone="danger">@csrf @method('DELETE')
                        <h3 class="text-sm font-semibold text-text-bold">{{ __('Désactiver') }}</h3>
                        <p class="text-sm text-text-main/55">{{ __('Le mot de passe seul suffira de nouveau pour se connecter.') }}</p>
                        <label class="grid gap-2 text-sm font-medium text-text-bold">{{ __('Mot de passe actuel') }}<input name="password" type="password" required autocomplete="current-password" class="{{ $input }}"></label>
                        <button class="rounded-xl border border-red-400/40 px-4 py-2.5 text-sm font-medium text-red-300 transition hover:bg-red-400/10">{{ __('Désactiver la double authentification') }}</button>
                    </form>
                </div>
                @error('password')<p class="mt-4 text-sm text-red-300">{{ $message }}</p>@enderror
            @else
                <ol class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[auto_minmax(0,1fr)]">
                    <li class="grid justify-items-center gap-3">
                        <div x-data="qrCode" data-value="{{ $provisioningUri }}" class="size-52 overflow-hidden rounded-2xl bg-white p-1 shadow-[0_0_40px_rgba(88,166,255,.18)]"></div>
                        <a href="{{ $provisioningUri }}" class="text-xs text-accent hover:underline lg:hidden">{{ __('Ouvrir dans mon application ↗') }}</a>
                    </li>
                    <li class="grid content-start gap-5">
                        <div>
                            <p class="text-sm font-semibold text-text-bold"><span class="mr-2 font-[var(--font-mono)] text-accent">1</span>{{ __('Scanne le QR code') }}</p>
                            <p class="mt-1 text-sm text-text-main/55">{{ __('Avec Aegis, 2FAS, Google Authenticator, 1Password, Bitwarden… Sinon, saisis cette clé :') }}</p>
                            <div class="mt-3 flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                                <code class="select-all break-words rounded-lg border border-border bg-bg px-3 py-2 font-[var(--font-mono)] text-sm tracking-wider text-text-bold">{{ trim(chunk_split($pendingSecret, 4, ' ')) }}</code>
                                <button type="button" class="rounded-lg border border-border px-3 py-2 text-xs text-text-main/70 transition hover:border-accent/40 hover:text-accent" @click="navigator.clipboard.writeText(@js($pendingSecret)).then(() => { copied = true; setTimeout(() => copied = false, 2000) })" x-text="copied ? @js(__('Copiée ✓')) : @js(__('Copier'))">{{ __('Copier') }}</button>
                            </div>
                        </div>
                        <form action="{{ route('admin.security.two-factor.enable') }}" method="POST" class="grid max-w-sm gap-3">@csrf
                            <label class="grid gap-2 text-sm font-semibold text-text-bold"><span><span class="mr-2 font-[var(--font-mono)] text-accent">2</span>{{ __('Saisis le code affiché') }}</span>
                                <input name="code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="one-time-code" required placeholder="123 456" class="{{ $input }} font-[var(--font-mono)] text-lg tracking-[.4em]">
                            </label>
                            @error('code')<span class="text-xs text-red-300">{{ $message }}</span>@enderror
                            <button class="rounded-xl bg-accent px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-sky-300">{{ __('Activer la double authentification') }}</button>
                        </form>
                    </li>
                </ol>
            @endif
        </section>

        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6 xl:col-span-2">
            <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('02 · Activité') }}</p>
                    <h2 class="mt-1 font-semibold text-text-bold">{{ __('Connexions récentes') }}</h2>
                    <p class="mt-2 max-w-2xl text-sm text-text-main/55">{{ __('Connexions, mots de passe et codes refusés, verrouillages. Conservé :value jours. Les alertes de connexion et de verrouillage partent sur tes canaux de notification, selon tes paramètres.', ['value' => \App\Models\LoginEvent::RETENTION_DAYS]) }}</p>
                </div>
                <form action="{{ route('admin.security.sessions.destroy') }}" method="POST" class="grid w-full gap-2 sm:flex sm:w-auto sm:items-end" data-confirm="{{ __('Tous les autres appareils connectés à l’administration seront déconnectés.') }}" data-confirm-title="{{ __('Déconnecter les autres sessions ?') }}" data-confirm-label="{{ __('Déconnecter') }}" data-confirm-tone="danger">@csrf
                    <label class="grid gap-1 text-xs font-medium text-text-bold sm:w-56">{{ __('Mot de passe actuel') }}<input name="password" type="password" required autocomplete="current-password" class="{{ $input }} py-2.5"></label>
                    <button class="rounded-xl border border-border px-4 py-2.5 text-sm text-text-main/80 transition hover:border-red-400/40 hover:text-red-300">{{ __('Déconnecter mes autres sessions') }}</button>
                    @error('password', 'sessions')<span class="text-xs text-red-300 sm:w-full">{{ $message }}</span>@enderror
                </form>
            </div>
            @if ($loginEvents->isEmpty())
                <p class="text-sm text-text-main/50">{{ __('Aucune activité enregistrée pour l’instant.') }}</p>
            @else
                <ul class="divide-y divide-border/60 md:hidden">
                    @foreach ($loginEvents as $event)
                        <li class="grid gap-1 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <span class="rounded-md border px-2 py-0.5 text-[11px] {{ $event->type->badgeClasses() }}">{{ $event->type->label() }}</span>
                                <span class="text-xs text-text-main/60">{{ $event->created_at->timezone(config('admin.timezone'))->format('d/m H:i') }}</span>
                            </div>
                            <p class="text-sm text-text-bold">{{ $event->deviceLabel() }}@if ($event->device_hash === $currentDeviceHash)<span class="ml-2 text-[11px] text-accent">{{ __('cet appareil') }}</span>@endif</p>
                            <p class="truncate font-[var(--font-mono)] text-[11px] text-text-main/55">{{ $event->ip_address }}@if ($event->email) · {{ $event->email }}@endif</p>
                        </li>
                    @endforeach
                </ul>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.15em] text-text-main/45"><tr><th class="pb-3 font-normal">{{ __('Date') }}</th><th class="pb-3 font-normal">{{ __('Événement') }}</th><th class="pb-3 font-normal">{{ __('Appareil') }}</th><th class="pb-3 font-normal">{{ __('IP') }}</th><th class="pb-3 font-normal">{{ __('Compte') }}</th></tr></thead>
                        <tbody class="divide-y divide-border/60">
                            @foreach ($loginEvents as $event)
                                <tr>
                                    <td class="py-2.5 pr-4 whitespace-nowrap text-text-main/70">{{ $event->created_at->timezone(config('admin.timezone'))->format('d/m H:i') }}</td>
                                    <td class="py-2.5 pr-4"><span class="rounded-md border px-2 py-1 text-[11px] {{ $event->type->badgeClasses() }}">{{ $event->type->label() }}</span></td>
                                    <td class="py-2.5 pr-4 text-text-bold">{{ $event->deviceLabel() }}@if ($event->device_hash === $currentDeviceHash)<span class="ml-2 text-[11px] text-accent">{{ __('cet appareil') }}</span>@endif</td>
                                    <td class="py-2.5 pr-4 font-[var(--font-mono)] text-xs text-text-main/70">{{ $event->ip_address }}</td>
                                    <td class="py-2.5 truncate text-xs text-text-main/60">{{ $event->email }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <aside class="grid content-start gap-4 rounded-2xl border border-border bg-card-bg p-5 text-sm sm:p-6 xl:col-start-2 xl:row-start-1">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Protections actives') }}</p>
            <ul class="grid gap-3 text-text-main/65">
                <li class="flex gap-3"><span class="text-green-300">✓</span>{{ __('Verrouillage du compte 15 min après 10 mots de passe erronés') }}</li>
                <li class="flex gap-3"><span class="text-green-300">✓</span>{{ __('Limitation des tentatives par adresse IP') }}</li>
                <li class="flex gap-3"><span class="text-green-300">✓</span>{{ __('Codes 2FA à usage unique, non rejouables') }}</li>
                <li class="flex gap-3"><span class="text-green-300">✓</span>{{ __('Secrets chiffrés en base avec la clé de l’application') }}</li>
                <li class="flex gap-3"><span class="text-green-300">✓</span>{{ __('En-têtes HTTP anti-clickjacking et HSTS') }}</li>
                <li class="flex gap-3"><span class="text-green-300">✓</span>{{ __('Journal des connexions et alertes nouvel appareil') }}</li>
            </ul>
        </aside>
    </div>
</x-admin.layout>
