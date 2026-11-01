@extends('errors.layout')

@section('code', (string) $exception->getStatusCode())
@section('title', __('Une perturbation dans le cosmos'))
@section('eyebrow', __('Exploration interrompue'))
@section('description', __('Le serveur rencontre un problème temporaire. Veuillez réessayer un peu plus tard.'))
