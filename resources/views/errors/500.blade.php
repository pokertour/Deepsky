@extends('errors.layout')

@section('code', '500')
@section('title', __('Une perturbation dans le cosmos'))
@section('eyebrow', __('Erreur du serveur'))
@section('description', __('Un problème inattendu interrompt votre exploration. Veuillez réessayer un peu plus tard.'))
@section('secondary-label', __('Réessayer'))
@section('secondary-url'){{ url()->current() }}@endsection
@section('hint', __('Si le problème persiste, revenez dans quelques minutes.'))
