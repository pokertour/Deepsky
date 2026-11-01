@extends('errors.layout')

@section('code', '401')
@section('title', __('Connexion requise'))
@section('eyebrow', __('Un accès réservé'))
@section('description', __('Connectez-vous pour poursuivre votre exploration de cet espace.'))
@section('secondary-label', __('Se connecter'))
@section('secondary-url'){{ route('admin.login') }}@endsection
@section('hint', __('Votre session vous permettra de retrouver votre espace.'))
