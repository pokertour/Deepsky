<x-mail::message>
# {{ __('Confirmez votre inscription') }}

{{ __('Vous avez demandé à recevoir un email à chaque nouvelle astrophoto publiée sur Deepsky.') }}

{{ __('Cliquez sur le bouton ci-dessous pour confirmer votre inscription :') }}

{{ __('Ce lien est valable :days jours. Après expiration, demandez un nouvel email depuis le formulaire du site.', ['days' => \App\Models\NewsletterSubscriber::CONFIRMATION_VALIDITY_DAYS]) }}

<x-mail::button :url="$confirmUrl">
{{ __('Confirmer mon inscription') }}
</x-mail::button>

{{ __('Si vous n’êtes pas à l’origine de cette demande, ignorez simplement cet email — vous ne recevrez rien.') }}

{{ __('À bientôt sous les étoiles,') }}<br>
{{ config('app.name') }}
</x-mail::message>
