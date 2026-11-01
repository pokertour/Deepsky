<x-mail::message>
# {{ __('Nouvelle image publiée') }}

**{{ $astrophoto->localized('title') }}**{{ $astrophoto->target ? ' — '.$astrophoto->target : '' }}

{{ __('Une nouvelle astrophoto vient d’être publiée sur Deepsky.') }}

<x-mail::button :url="route('astrophoto.show', $astrophoto)">
{{ __('Découvrir la photo') }}
</x-mail::button>

{{ __('À bientôt sous les étoiles,') }}<br>
{{ config('app.name') }}

<x-mail::subcopy>
{{ __('Vous recevez cet email car vous êtes inscrit à la newsletter Deepsky.') }}
[{{ __('Se désinscrire') }}]({{ $unsubscribeUrl }})
</x-mail::subcopy>
</x-mail::message>
