@extends('layouts.app')

@section('titre', 'Offre — Passerelle')

@section('contenu')
    <p><a href="{{ route('etudiant.offres') }}">← Retour aux offres</a></p>
    <h1>{{ $offre->intitule }}</h1>
    <p class="sous-titre">
        {{ $offre->entreprise->raison_sociale }} · {{ $offre->type }} · {{ $offre->niveau }} ·
        {{ $offre->domaine->libelle }} · {{ $offre->lieu }}
    </p>

    <div class="carte">
        <p><strong>Période :</strong> {{ $offre->date_debut_prevue->format('d/m/Y') }} → {{ $offre->date_fin_prevue->format('d/m/Y') }}
            · <strong>Postes :</strong> {{ $offre->nb_postes }}</p>
        <p>{{ $offre->description }}</p>
        <p class="a-venir">Candidature en ligne : module à venir.</p>
    </div>
@endsection
