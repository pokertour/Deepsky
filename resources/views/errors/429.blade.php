@extends('errors.layout')

@section('code', '429')
@section('title', __('Un peu de calme sous les étoiles'))
@section('eyebrow', __('Trop de requêtes'))
@section('description', __('Vous avez envoyé trop de demandes en peu de temps. Patientez un moment avant de reprendre votre exploration.'))
@section('hint', __('Attendez quelques instants avant de réessayer.'))
