@extends('errors.layout')

@section('code', '503')
@section('title', __('Une pause sous les étoiles'))
@section('eyebrow', __('Service indisponible'))
@section('description', __('Deepsky est temporairement indisponible, peut-être pour une maintenance. Revenez bientôt pour retrouver le ciel profond.'))
@section('secondary-label', __('Réessayer'))
@section('secondary-url'){{ url()->current() }}@endsection
@section('hint', __('Merci de votre patience. Les étoiles seront toujours là.'))
