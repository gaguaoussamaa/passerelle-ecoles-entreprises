@extends('layouts.app')

@section('titre', 'Fiche étudiant — Passerelle')

@section('contenu')
    <p><a href="{{ route('ecole.etudiants') }}">← Retour aux étudiants</a></p>
    <h1>{{ $etudiant->prenom }} {{ $etudiant->nom }}</h1>
    <p class="sous-titre">
        {{ $etudiant->compte->email }} ·
        {{ $etudiant->promotion->libelle }} ({{ $etudiant->promotion->formation->intitule }}) ·
        compte {{ $etudiant->compte->mot_de_passe ? 'activé' : 'non activé' }}
    </p>

    <div class="grille-cartes">
        <div class="carte">
            <h2>Statut de scolarité</h2>
            <p class="sous-titre">L'accès à la plateforme en découle (RG-05) ; tout changement est réversible et tracé (EF-33).</p>
            <form method="POST" action="{{ route('ecole.etudiants.statut', $etudiant->compte_id) }}">
                @csrf
                <select name="statut">
                    @foreach ($statuts as $s)
                        <option value="{{ $s }}" @selected($etudiant->statut_scolarite === $s)>{{ $s }}</option>
                    @endforeach
                </select>
                <button class="bouton bouton-court">Appliquer</button>
            </form>
        </div>

        <div class="carte">
            <h2>Promotion</h2>
            <form method="POST" action="{{ route('ecole.etudiants.promotion', $etudiant->compte_id) }}">
                @csrf
                <select name="promotion_id">
                    @foreach ($promotions as $promotion)
                        <option value="{{ $promotion->id }}" @selected($etudiant->promotion_id === $promotion->id)>
                            {{ $promotion->libelle }} — {{ $promotion->formation->intitule }}
                        </option>
                    @endforeach
                </select>
                <button class="bouton bouton-court">Changer de promotion</button>
            </form>
        </div>

        <div class="carte">
            <h2>Invitation</h2>
            @if ($etudiant->compte->mot_de_passe)
                <p class="sous-titre">Compte déjà activé — aucun renvoi nécessaire.</p>
            @else
                <p class="sous-titre">Le renvoi invalide l'ancien lien (RG-02).</p>
                <form method="POST" action="{{ route('ecole.etudiants.invitation', $etudiant->compte_id) }}">
                    @csrf
                    <button class="bouton bouton-court">Renvoyer l'invitation</button>
                </form>
            @endif
        </div>

        <div class="carte">
            <h2>Suppression</h2>
            @if ($dossierVierge)
                <p class="sous-titre">Dossier vierge : suppression définitive possible (RG-15).</p>
                <form method="POST" action="{{ route('ecole.etudiants.supprimer', $etudiant->compte_id) }}"
                      onsubmit="return confirm('Supprimer définitivement ce dossier vierge ?')">
                    @csrf
                    @method('DELETE')
                    <button class="bouton bouton-court bouton-danger">Supprimer le dossier</button>
                </form>
            @else
                <p class="sous-titre">Dossier non vierge : la suppression est bloquée (RG-15) — utilisez un statut « diplômé » ou « sorti ».</p>
            @endif
        </div>
    </div>
@endsection
