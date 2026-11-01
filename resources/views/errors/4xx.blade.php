@extends('errors.layout')

@section('code', (string) $exception->getStatusCode())
@section('title', __('Cette trajectoire est inaccessible'))
@section('eyebrow', __('Exploration interrompue'))
@section('description', __('Cette demande ne peut pas aboutir. Revenez à la galerie pour poursuivre votre exploration.'))
