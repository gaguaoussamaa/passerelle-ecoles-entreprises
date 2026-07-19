@extends('layouts.app')

@section('titre', 'Tuteurs pédagogiques — Passerelle')

@section('contenu')
    <h1>Tuteurs pédagogiques</h1>
    <p class="sous-titre">Enseignants de l'établissement — la création envoie une invitation d'activation (RG-01).</p>

    <div class="carte">
        <table class="tableau">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Formations rattachées</th><th>Compte</th></tr></thead>
            <tbody>
            @forelse ($tuteurs as $tuteur)
                <tr>
                    <td>{{ $tuteur->prenom }} {{ $tuteur->nom }}</td>
                    <td>{{ $tuteur->compte->email }}</td>
                    <td>{{ $tuteur->formations->pluck('intitule')->join(', ') ?: '—' }}</td>
                    <td>{!! $tuteur->compte->mot_de_passe ? '<span class="badge badge-vert">activé</span>' : '<span class="badge badge-orange">invité</span>' !!}</td>
                </tr>
            @empty
                <tr><td colspan="4">Aucun tuteur pour l'instant.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="carte">
        <h2>Créer un tuteur</h2>
        <form method="POST" action="{{ route('ecole.tuteurs.creer') }}">
            @csrf
            <div class="grille-form">
                <div><label for="prenom">Prénom</label><input id="prenom" name="prenom" required></div>
                <div><label for="nom">Nom</label><input id="nom" name="nom" required></div>
                <div><label for="email">E-mail</label><input id="email" name="email" type="email" required></div>
            </div>
            <label>Formations de rattachement (RG-28 : nécessaires pour encadrer)</label>
            <div class="cases">
                @foreach ($formations as $formation)
                    <label class="case"><input type="checkbox" name="formations[]" value="{{ $formation->id }}"> {{ $formation->intitule }}</label>
                @endforeach
            </div>
            <button class="bouton bouton-court">Créer et inviter</button>
        </form>
    </div>
@endsection
