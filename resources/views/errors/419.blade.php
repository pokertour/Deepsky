@extends('errors.layout')

@section('code', '419')
@section('title', __('Votre session s’est éclipsée'))
@section('eyebrow', __('Session expirée'))
@section('description', __('La page est restée ouverte trop longtemps. Revenez à votre espace et recommencez votre action.'))
@section('secondary-label', __('Se reconnecter'))
@section('secondary-url'){{ route('admin.login') }}@endsection
@section('hint', __('Si vous aviez rempli un formulaire, il faudra le soumettre à nouveau.'))
