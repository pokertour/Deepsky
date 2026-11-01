@extends('errors.layout')

@section('code', '502')
@section('title', __('Le signal s’est perdu'))
@section('eyebrow', __('Passerelle indisponible'))
@section('description', __('Un service nécessaire n’a pas répondu correctement. Votre exploration pourra reprendre dès que la liaison sera rétablie.'))
@section('secondary-label', __('Réessayer'))
@section('secondary-url'){{ url()->current() }}@endsection
@section('hint', __('Patientez quelques instants avant de réessayer.'))
