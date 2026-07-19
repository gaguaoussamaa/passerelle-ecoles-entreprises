@extends('layouts.app')

@section('titre', 'Promotions — Passerelle')

@section('contenu')
    <h1>Promotions</h1>
    <p class="sous-titre">Groupes d'étudiants par formation et année universitaire (archivage sans perte — RG-11).</p>

    <div class="carte">
        <table class="tableau">
            <thead><tr><th>Libellé</th><th>Formation</th><th>Année</th><th>Étudiants</th><th>État</th><th></th></tr></thead>
            <tbody>
            @forelse ($promotions as $promotion)
                <tr class="{{ $promotion->archivee ? 'ligne-archivee' : '' }}">
                    <td>{{ $promotion->libelle }}</td>
                    <td>{{ $promotion->formation->intitule }}</td>
                    <td>{{ $promotion->annee_universitaire }}</td>
                    <td>{{ $promotion->etudiants_count }}</td>
                    <td>{!! $promotion->archivee ? '<span class="badge badge-gris">archivée</span>' : '<span class="badge badge-vert">active</span>' !!}</td>
                    <td>
                        <form method="POST" action="{{ route('ecole.promotions.archiver', $promotion->id) }}">
                            @csrf
                            <button class="lien-sobre">{{ $promotion->archivee ? 'réactiver' : 'archiver' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6">Aucune promotion pour l'instant.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="carte">
        <h2>Créer une promotion</h2>
        <form method="POST" action="{{ route('ecole.promotions.creer') }}">
            @csrf
            <div class="grille-form">
                <div>
                    <label for="formation_id">Formation</label>
                    <select id="formation_id" name="formation_id" required>
                        @foreach ($formations as $formation)
                            <option value="{{ $formation->id }}">{{ $formation->intitule }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="libelle">Libellé</label>
                    <input id="libelle" name="libelle" placeholder="ex. M2 Dev 2026-2027" required>
                </div>
                <div>
                    <label for="annee_universitaire">Année universitaire</label>
                    <input id="annee_universitaire" name="annee_universitaire" placeholder="2026-2027" pattern="\d{4}-\d{4}" required>
                </div>
            </div>
            <button class="bouton bouton-court">Créer la promotion</button>
        </form>
    </div>
@endsection
