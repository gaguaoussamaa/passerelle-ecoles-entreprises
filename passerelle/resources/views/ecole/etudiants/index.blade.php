@extends('layouts.app')

@section('titre', 'Étudiants — Passerelle')

@section('contenu')
    <h1>Étudiants</h1>
    <p class="sous-titre">
        Dossiers étudiants de l'établissement —
        <a href="{{ route('ecole.etudiants.import') }}">import CSV</a> ·
        <a href="{{ route('ecole.etudiants.import.modele') }}">modèle de fichier</a>
    </p>

    <div class="carte">
        <form method="GET" action="{{ route('ecole.etudiants') }}" class="filtres">
            <select name="promotion" onchange="this.form.submit()">
                <option value="">Toutes les promotions</option>
                @foreach ($promotions as $promotion)
                    <option value="{{ $promotion->id }}" @selected($filtres['promotion'] === $promotion->id)>
                        {{ $promotion->libelle }} — {{ $promotion->formation->intitule }}
                    </option>
                @endforeach
            </select>
            <select name="statut" onchange="this.form.submit()">
                <option value="">Tous les statuts</option>
                @foreach (\App\Models\Etudiant::STATUTS as $s)
                    <option value="{{ $s }}" @selected($filtres['statut'] === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </form>

        <table class="tableau">
            <thead><tr><th>Nom</th><th>E-mail</th><th>Promotion</th><th>Statut</th><th>Compte</th><th></th></tr></thead>
            <tbody>
            @forelse ($etudiants as $etudiant)
                <tr>
                    <td>{{ $etudiant->prenom }} {{ $etudiant->nom }}</td>
                    <td>{{ $etudiant->compte->email }}</td>
                    <td>{{ $etudiant->promotion->libelle }}</td>
                    <td><span class="badge badge-{{ $etudiant->statut_scolarite === 'actif' ? 'vert' : ($etudiant->statut_scolarite === 'invite' ? 'orange' : 'gris') }}">{{ $etudiant->statut_scolarite }}</span></td>
                    <td>{{ $etudiant->compte->mot_de_passe ? 'activé' : 'non activé' }}</td>
                    <td><a href="{{ route('ecole.etudiants.fiche', $etudiant->compte_id) }}">fiche</a></td>
                </tr>
            @empty
                <tr><td colspan="6">Aucun étudiant ne correspond aux filtres.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="carte">
        <h2>Créer un étudiant (l'invitation part automatiquement)</h2>
        <form method="POST" action="{{ route('ecole.etudiants.creer') }}">
            @csrf
            <div class="grille-form">
                <div><label for="prenom">Prénom</label><input id="prenom" name="prenom" required></div>
                <div><label for="nom">Nom</label><input id="nom" name="nom" required></div>
                <div><label for="email">E-mail</label><input id="email" name="email" type="email" required></div>
                <div>
                    <label for="promotion_id">Promotion</label>
                    <select id="promotion_id" name="promotion_id" required>
                        @foreach ($promotions as $promotion)
                            <option value="{{ $promotion->id }}">{{ $promotion->libelle }} — {{ $promotion->formation->intitule }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button class="bouton bouton-court">Créer et inviter</button>
        </form>
    </div>
@endsection
