@extends('errors.layout')

@section('code', '404')
@section('title', __('Perdu dans les étoiles ?'))
@section('eyebrow', __('Coordonnées introuvables'))
@section('description', __('Cette page semble avoir quitté notre orbite. Le lien est peut-être ancien, ou la page a été déplacée.'))
@section('secondary-label', __('Voir l’observatoire'))
@section('secondary-url'){{ route('observatory') }}@endsection
@section('hint', __('Vérifiez l’adresse, ou reprenez votre exploration depuis la galerie.'))
