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
    </div>

    <div class="carte">
        <h2>Ma candidature</h2>
        @if (session('succes'))
            <div class="message message-succes">{{ session('succes') }}</div>
        @endif
        @if ($errors->any())
            <div class="message message-erreur">{{ $errors->first() }}</div>
        @endif

        @if ($candidature)
            <p>Candidature déposée le {{ $candidature->created_at->format('d/m/Y') }} —
                statut : <strong>{{ $candidature->libelleStatut() }}</strong>
                (suivi sur la page <a href="{{ route('etudiant.candidatures') }}">Mes candidatures</a>).</p>
        @elseif (! $etudiant->cv_profil)
            <p>Déposez d'abord votre CV depuis la page
                <a href="{{ route('etudiant.candidatures') }}">Mes candidatures</a> pour candidater.</p>
        @else
            <form method="POST" action="{{ route('etudiant.offres.candidater', $offre->id) }}">
                @csrf
                <label for="message">Message de motivation</label>
                <textarea id="message" name="message" rows="4" required minlength="10"
                    placeholder="Présentez votre parcours et votre motivation pour cette mission.">{{ old('message') }}</textarea>
                <p><small>Votre CV de profil sera joint tel quel : une copie est figée avec la candidature.</small></p>
                <button class="bouton bouton-court">Envoyer ma candidature</button>
            </form>
        @endif
    </div>
@endsection
