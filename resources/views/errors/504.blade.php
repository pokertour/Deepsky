@extends('errors.layout')

@section('code', '504')
@section('title', __('Le signal se fait attendre'))
@section('eyebrow', __('Délai dépassé'))
@section('description', __('Un service met trop de temps à répondre. Patientez un moment, puis tentez à nouveau votre exploration.'))
@section('secondary-label', __('Réessayer'))
@section('secondary-url'){{ url()->current() }}@endsection
@section('hint', __('Patientez quelques instants avant de réessayer.'))
