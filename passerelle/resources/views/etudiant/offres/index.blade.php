@extends('layouts.app')

@section('titre', 'Offres — Passerelle')

@section('contenu')
    <h1>Offres pour ma promotion</h1>
    <p class="sous-titre">Offres validées par votre établissement et ciblant votre promotion.</p>

    <div class="grille-cartes">
        @forelse ($offres as $offre)
            <div class="carte">
                <h2>{{ $offre->intitule }}</h2>
                <p class="sous-titre">{{ $offre->entreprise->raison_sociale }} · {{ $offre->type }} · {{ $offre->lieu }}</p>
                <p><small>{{ $offre->date_debut_prevue->format('d/m/Y') }} → {{ $offre->date_fin_prevue->format('d/m/Y') }} · {{ $offre->nb_postes }} poste(s)</small></p>
                <a href="{{ route('etudiant.offres.detail', $offre->id) }}">Voir l'offre →</a>
            </div>
        @empty
            <div class="carte"><p>Aucune offre visible pour votre promotion actuellement.</p></div>
        @endforelse
    </div>
@endsection
