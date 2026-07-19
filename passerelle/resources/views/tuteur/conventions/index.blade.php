@extends('layouts.app')

@section('titre', 'Conventions — Passerelle')

@section('contenu')
    <h1>Conventions de mes étudiants</h1>
    <p class="sous-titre">Validez la cohérence pédagogique à votre tour, puis approuvez la version validée.</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @forelse ($missions as $mission)
        <div class="carte">
            <h2>{{ $mission->etudiant->prenom }} {{ $mission->etudiant->nom }}
                — {{ $mission->entreprise->raison_sociale }} ({{ $mission->type }})</h2>
            <p class="sous-titre">{{ $mission->etudiant->promotion->formation->intitule }} ·
                {{ $mission->date_debut->format('d/m/Y') }} → {{ $mission->date_fin->format('d/m/Y') }} ·
                mission {{ str_replace('_', ' ', $mission->statut) }}</p>
            @if ($mission->versionsConvention->isEmpty())
                <p>Convention pas encore générée.</p>
            @else
                @include('partials.convention', ['mission' => $mission, 'role' => 'tuteur_pedagogique'])
            @endif
        </div>
    @empty
        <div class="carte"><p>Aucune mission ne vous est confiée pour le moment.</p></div>
    @endforelse
@endsection
