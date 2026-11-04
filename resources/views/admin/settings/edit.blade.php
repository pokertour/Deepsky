<x-admin.layout title="{{ __('Paramètres') }}" eyebrow="{{ __('Configuration système') }}">
    @php($input = 'w-full rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none transition placeholder:text-text-main/30 focus:border-accent focus:ring-4 focus:ring-accent/10')
    @php($label = 'grid gap-2 text-sm font-medium text-text-bold')
    @php($checkbox = 'size-4 rounded border-border bg-bg accent-accent')
    <form action="{{ route('admin.settings.update') }}" method="POST" class="grid gap-6">@csrf @method('PUT')
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('01 · Rédaction') }}</p>
            <h2 class="mt-1 font-semibold text-text-bold">{{ __('Présentations astronomiques avec Gemini') }}</h2>
            <p class="mt-2 text-sm text-text-main/50">{{ __('Génère une proposition de texte accessible depuis le formulaire des photos. Seules les informations publiques du catalogue sont envoyées à Google.') }}</p>
            <div class="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <label class="{{ $label }}">{{ __('Clé API Gemini') }}<input name="gemini_api_key" type="password" autocomplete="new-password" maxlength="500" class="{{ $input }}" placeholder="{{ __('Laisser vide pour conserver la clé actuelle') }}"><span class="text-[11px] font-normal text-text-main/40">{{ __(':value La clé est stockée chiffrée.', ['value' => filled($integrations->gemini_api_key) ? __('Une clé Gemini est configurée.') : __('Aucune clé Gemini configurée.')]) }}</span>@error('gemini_api_key')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
                <label class="{{ $label }}">{{ __('Modèle Gemini') }}<input name="gemini_model" value="{{ old('gemini_model', $integrations->gemini_model) }}" required maxlength="100" class="{{ $input }}" placeholder="{{ __('gemini-2.5-flash-lite') }}">@error('gemini_model')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
            </div>
            <p class="mt-4 text-xs leading-relaxed text-text-main/45">{{ __('Le niveau gratuit dépend du modèle et des quotas de ton projet Google. Un projet avec facturation activée peut entraîner des frais. Les conditions Google peuvent autoriser la réutilisation du contenu envoyé.') }}</p>
            <div class="mt-3 flex flex-wrap gap-4 text-xs text-accent"><a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener noreferrer">{{ __('Créer une clé dans Google AI Studio ↗') }}</a><a href="https://ai.google.dev/gemini-api/docs/pricing" target="_blank" rel="noopener noreferrer">{{ __('Tarifs et niveau gratuit ↗') }}</a></div>
        </section>
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6"><div class="mb-6"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('02 · Intégrations') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Services astronomiques') }}</h2><p class="mt-2 text-sm text-text-main/50">{{ __('Les secrets sont chiffrés avant leur stockage.') }}</p></div><label class="{{ $label }} max-w-2xl">{{ __('Clé API Astrometry.net') }}<input name="astrometry_api_key" type="password" autocomplete="new-password" class="{{ $input }}" placeholder="{{ __('Laisser vide pour conserver la clé actuelle') }}"><span class="text-[11px] font-normal text-text-main/40">{{ filled($integrations->astrometry_api_key) ? __('Une clé est actuellement configurée.') : __('Aucune clé configurée.') }}</span></label>
            <div class="mt-6 border-t border-border pt-5">
                <h3 class="text-sm font-semibold text-text-bold">{{ __('Mesure d’audience Umami') }}</h3>
                <p class="mt-1 text-xs text-text-main/50">{{ __('Facultatif. Le script est ajouté aux pages publiques uniquement (jamais à l’administration) et son domaine est autorisé dans la politique de sécurité.') }}</p>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="{{ $label }}">{{ __('URL du script') }}<input name="umami_script_url" type="url" inputmode="url" value="{{ old('umami_script_url', $integrations->umami_script_url) }}" placeholder="https://umami.example.com/script.js" class="{{ $input }}">@error('umami_script_url')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
                    <label class="{{ $label }}">{{ __('ID du site') }}<input name="umami_website_id" value="{{ old('umami_website_id', $integrations->umami_website_id) }}" placeholder="00000000-0000-0000-0000-000000000000" class="{{ $input }} font-[var(--font-mono)]">@error('umami_website_id')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
                </div>
            </div>
        </section>
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6" x-data="{ provider: '{{ old('captcha_provider', $integrations->captcha_provider->value) }}' }"><div class="mb-6"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('03 · Protection') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Captcha newsletter') }}</h2></div><div class="grid grid-cols-1 gap-5 lg:grid-cols-3"><label class="{{ $label }}">{{ __('Fournisseur') }}<select name="captcha_provider" x-model="provider" class="{{ $input }}">@foreach(\App\Enums\CaptchaProvider::cases() as $provider)<option value="{{ $provider->value }}">{{ $provider->getLabel() }}</option>@endforeach</select></label><label x-show="provider !== 'none'" x-cloak class="{{ $label }}">{{ __('Clé de site') }}<input name="captcha_site_key" value="{{ old('captcha_site_key', $integrations->captcha_site_key) }}" class="{{ $input }}"></label><label x-show="provider !== 'none'" x-cloak class="{{ $label }}">{{ __('Clé secrète') }}<input name="captcha_secret_key" type="password" autocomplete="new-password" class="{{ $input }}" placeholder="{{ __('Laisser vide pour conserver') }}"><span class="text-[11px] font-normal text-text-main/40">{{ filled($integrations->captcha_secret_key) ? __('Une clé secrète est enregistrée.') : __('Aucune clé secrète enregistrée.') }}</span></label></div>@if ($integrations->captcha_provider !== \App\Enums\CaptchaProvider::None)<div class="mt-4 flex flex-wrap items-center gap-3 border-t border-border pt-4"><button type="submit" form="captcha-test-form" class="rounded-lg border border-accent/30 bg-accent/10 px-3 py-2 text-xs font-medium text-accent transition hover:bg-accent/20">{{ __('Tester la clé secrète') }}</button><span class="text-[11px] text-text-main/45">{{ __('Appelle Siteverify avec la clé enregistrée pour vérifier qu’elle est acceptée.') }}</span></div>@endif</section>
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6"><div class="mb-6"><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('04 · Transmission') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Serveur SMTP') }}</h2><p class="mt-2 text-sm text-text-main/50">{{ __('Utilisé pour la newsletter et les notifications email.') }}</p></div><div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3"><label class="{{ $label }} sm:col-span-2">{{ __('Hôte') }}<input name="host" value="{{ old('host', $mail->host) }}" required class="{{ $input }}" placeholder="{{ __('smtp.example.com') }}">@error('host')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label><label class="{{ $label }}">{{ __('Port') }}<input name="port" type="number" min="1" max="65535" value="{{ old('port', $mail->port) }}" required class="{{ $input }}"></label><label class="{{ $label }}">{{ __('Utilisateur') }}<input name="username" value="{{ old('username', $mail->username) }}" class="{{ $input }}"></label><label class="{{ $label }}">{{ __('Mot de passe') }}<input name="password" type="password" autocomplete="new-password" class="{{ $input }}" placeholder="{{ __('Laisser vide pour conserver') }}"></label><label class="{{ $label }}">{{ __('Chiffrement') }}<select name="scheme" class="{{ $input }}"><option value="">{{ __('Automatique') }}</option><option value="smtp" @selected(old('scheme', $mail->scheme) === 'smtp')>{{ __('SMTP') }}</option><option value="smtps" @selected(old('scheme', $mail->scheme) === 'smtps')>{{ __('SMTPS') }}</option><option value="tls" @selected(old('scheme', $mail->scheme) === 'tls')>{{ __('STARTTLS') }}</option></select></label><label class="{{ $label }} sm:col-span-2">{{ __('Adresse expéditeur') }}<input name="from_address" type="email" value="{{ old('from_address', $mail->from_address) }}" required class="{{ $input }}"></label><label class="{{ $label }}">{{ __('Nom expéditeur') }}<input name="from_name" value="{{ old('from_name', $mail->from_name) }}" required class="{{ $input }}"></label></div></section>
        <div class="flex justify-end"><button class="w-full rounded-xl bg-accent px-6 py-3.5 text-sm font-semibold text-slate-950 hover:bg-sky-300 sm:w-auto">{{ __('Enregistrer les paramètres') }}</button></div>
    </form>
    <form id="captcha-test-form" method="POST" action="{{ route('admin.settings.captcha.test') }}" class="hidden">@csrf</form>

    <form action="{{ route('admin.settings.notifications.update') }}" method="POST" class="mt-6 grid gap-6">@csrf @method('PUT')
        <section class="rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
            <div class="mb-6">
                <p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('05 · NightScope') }}</p>
                <h2 class="mt-1 font-semibold text-text-bold">{{ __('Notifications') }}</h2>
                <p class="mt-2 text-sm text-text-main/50">{{ __('Telegram, Gotify, ntfy ou email. Les secrets sont chiffrés.') }}</p>
            </div>

            <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
                <div class="grid gap-4 rounded-xl border border-border bg-bg/40 p-4">
                    <label class="flex items-center gap-3 text-sm font-medium text-text-bold"><input type="hidden" name="telegram_enabled" value="0"><input class="{{ $checkbox }}" type="checkbox" name="telegram_enabled" value="1" @checked(old('telegram_enabled', $notifications->telegram_enabled))>{{ __('Telegram') }}</label>
                    <label class="{{ $label }}">{{ __('Token bot') }}<input name="telegram_bot_token" type="password" autocomplete="new-password" class="{{ $input }}" placeholder="{{ __('Laisser vide pour conserver') }}"><span class="text-[11px] font-normal text-text-main/40">{{ filled($notifications->telegram_bot_token) ? __('Token configuré.') : __('Aucun token.') }}</span></label>
                    <label class="{{ $label }}">{{ __('Chat ID') }}<input name="telegram_chat_id" value="{{ old('telegram_chat_id', $notifications->telegram_chat_id) }}" class="{{ $input }}" placeholder="-1001234567890">@error('telegram_chat_id')<span class="text-xs text-red-300">{{ $message }}</span>@enderror</label>
                </div>

                <div class="grid gap-4 rounded-xl border border-border bg-bg/40 p-4">
                    <label class="flex items-center gap-3 text-sm font-medium text-text-bold"><input type="hidden" name="gotify_enabled" value="0"><input class="{{ $checkbox }}" type="checkbox" name="gotify_enabled" value="1" @checked(old('gotify_enabled', $notifications->gotify_enabled))>{{ __('Gotify') }}</label>
                    <label class="{{ $label }}">{{ __('URL serveur') }}<input name="gotify_url" value="{{ old('gotify_url', $notifications->gotify_url) }}" class="{{ $input }}" placeholder="{{ __('https://gotify.example.com') }}"></label>
                    <label class="{{ $label }}">{{ __('Token application') }}<input name="gotify_token" type="password" autocomplete="new-password" class="{{ $input }}" placeholder="{{ __('Laisser vide pour conserver') }}"></label>
                </div>

                <div class="grid gap-4 rounded-xl border border-border bg-bg/40 p-4">
                    <label class="flex items-center gap-3 text-sm font-medium text-text-bold"><input type="hidden" name="ntfy_enabled" value="0"><input class="{{ $checkbox }}" type="checkbox" name="ntfy_enabled" value="1" @checked(old('ntfy_enabled', $notifications->ntfy_enabled))>{{ __('ntfy') }}</label>
                    <label class="{{ $label }}">{{ __('URL serveur') }}<input name="ntfy_url" value="{{ old('ntfy_url', $notifications->ntfy_url ?: 'https://ntfy.sh') }}" class="{{ $input }}"></label>
                    <label class="{{ $label }}">{{ __('Topic') }}<input name="ntfy_topic" value="{{ old('ntfy_topic', $notifications->ntfy_topic) }}" class="{{ $input }}" placeholder="{{ __('deepsky-nights') }}"></label>
                </div>

                <div class="grid gap-4 rounded-xl border border-border bg-bg/40 p-4">
                    <label class="flex items-center gap-3 text-sm font-medium text-text-bold"><input type="hidden" name="mail_enabled" value="0"><input class="{{ $checkbox }}" type="checkbox" name="mail_enabled" value="1" @checked(old('mail_enabled', $notifications->mail_enabled))>{{ __('Email') }}</label>
                    <label class="{{ $label }}">{{ __('Destinataire') }}<input name="mail_recipient" type="email" value="{{ old('mail_recipient', $notifications->mail_recipient) }}" class="{{ $input }}" placeholder="admin@deepsky.fr"></label>
                    <p class="text-[11px] text-text-main/40">{{ __('Utilise le SMTP configuré ci-dessus.') }}</p>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <fieldset class="rounded-xl border border-border bg-bg/40 p-4">
                    <legend class="px-2 text-sm font-semibold text-text-bold">{{ __('Alertes') }}</legend>
                    <div class="mt-3 grid gap-3">
                        <label class="flex items-center gap-3 text-sm text-text-main/80"><input type="hidden" name="alert_daily_conditions" value="0"><input class="{{ $checkbox }}" type="checkbox" name="alert_daily_conditions" value="1" @checked(old('alert_daily_conditions', $notifications->alert_daily_conditions))>{{ __('Bonne nuit (score ≥ seuil)') }}</label>
                        <label class="flex items-center gap-3 text-sm text-text-main/80"><input type="hidden" name="alert_best_targets" value="0"><input class="{{ $checkbox }}" type="checkbox" name="alert_best_targets" value="1" @checked(old('alert_best_targets', $notifications->alert_best_targets))>{{ __('Top cibles du soir') }} <span class="text-text-main/40">{{ __('(si nuit favorable)') }}</span></label>
                        <label class="flex items-center gap-3 text-sm text-text-main/80"><input type="hidden" name="alert_weekly_report" value="0"><input class="{{ $checkbox }}" type="checkbox" name="alert_weekly_report" value="1" @checked(old('alert_weekly_report', $notifications->alert_weekly_report))>{{ __('Rapport hebdomadaire (lundi)') }}</label>
                        <label class="flex items-center gap-3 text-sm text-text-main/80"><input type="hidden" name="alert_security_events" value="0"><input class="{{ $checkbox }}" type="checkbox" name="alert_security_events" value="1" @checked(old('alert_security_events', $notifications->alert_security_events))>{{ __('Sécurité : connexion depuis un nouvel appareil, compte verrouillé') }}</label>
                        <label class="flex items-center gap-3 pl-7 text-sm text-text-main/80"><input type="hidden" name="alert_every_login" value="0"><input class="{{ $checkbox }}" type="checkbox" name="alert_every_login" value="1" @checked(old('alert_every_login', $notifications->alert_every_login))>{{ __('Signaler aussi chaque connexion depuis un appareil déjà connu') }}</label>
                        <label class="flex items-center gap-3 text-sm text-text-main/80"><input type="hidden" name="alert_task_failures" value="0"><input class="{{ $checkbox }}" type="checkbox" name="alert_task_failures" value="1" @checked(old('alert_task_failures', $notifications->alert_task_failures))>{{ __('Échec d’une tâche ou d’un traitement') }} <span class="text-text-main/40">{{ __('(1 alerte max. par heure et par tâche)') }}</span></label>
                    </div>
                </fieldset>
                <div class="grid gap-4 rounded-xl border border-border bg-bg/40 p-4">
                    <label class="{{ $label }}">{{ __('Langue des notifications') }}<select name="locale" class="{{ $input }}">@foreach (App\Support\Locales::all() as $code => $name)<option value="{{ $code }}" @selected(old('locale', $notifications->locale) === $code)>{{ $name }}</option>@endforeach</select></label>
                    <label class="{{ $label }}">{{ __('Seuil night score') }}<input name="daily_min_score" type="number" min="0" max="100" value="{{ old('daily_min_score', $notifications->daily_min_score) }}" class="{{ $input }}"></label>
                    <label class="{{ $label }}">{{ __('Heure alerte quotidienne') }}<input name="daily_time" type="time" value="{{ old('daily_time', $notifications->daily_time) }}" class="{{ $input }}"></label>
                    <label class="{{ $label }}">{{ __('Heure rapport hebdo') }}<input name="weekly_time" type="time" value="{{ old('weekly_time', $notifications->weekly_time) }}" class="{{ $input }}"></label>
                </div>
            </div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
                <button class="rounded-xl bg-accent px-6 py-3.5 text-sm font-semibold text-slate-950 hover:bg-sky-300">{{ __('Enregistrer les notifications') }}</button>
            </div>
        </section>
    </form>

    <section class="mt-6 rounded-2xl border border-border bg-card-bg p-5 sm:p-6">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
            <div><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Diagnostic') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Tester les canaux') }}</h2></div>
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <form action="{{ route('admin.settings.notifications.test') }}" method="POST">@csrf<button class="rounded-xl border border-accent/30 bg-accent/10 px-5 py-3 text-sm font-medium text-accent hover:bg-accent/15">{{ __('Notification test') }}</button></form>
            </div>
        </div>
    </section>

    <section class="mt-6 rounded-2xl border border-border bg-card-bg p-5 sm:p-6"><div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between"><div><p class="font-[var(--font-mono)] text-[10px] uppercase tracking-[.18em] text-accent">{{ __('Diagnostic') }}</p><h2 class="mt-1 font-semibold text-text-bold">{{ __('Tester la connexion SMTP') }}</h2><p class="mt-2 text-sm text-text-main/50">{{ __('Le test utilise les derniers paramètres enregistrés.') }}</p></div><form action="{{ route('admin.settings.test') }}" method="POST" class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">@csrf<input name="recipient" type="email" required value="{{ auth()->user()->email }}" class="rounded-xl border border-border bg-bg px-4 py-3 text-sm text-text-bold outline-none focus:border-accent"><button class="rounded-xl border border-accent/30 bg-accent/10 px-5 py-3 text-sm font-medium text-accent hover:bg-accent/15">{{ __('Envoyer le test') }}</button></form></div></section>
</x-admin.layout>
