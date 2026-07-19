@extends('layouts.app')

@section('titre', 'Import CSV — Passerelle')

@section('contenu')
    <p><a href="{{ route('ecole.etudiants') }}">← Retour aux étudiants</a></p>
    <h1>Importer des étudiants (CSV)</h1>
    <p class="sous-titre">
        Format : <code>nom;prenom;email;promotion</code> —
        <a href="{{ route('ecole.etudiants.import.modele') }}">télécharger le modèle</a>.
        Les lignes valides créent un compte « invité » (invitation envoyée) ; les invalides sont rejetées avec motif (RG-13).
    </p>

    <div class="carte">
        <form method="POST" action="{{ route('ecole.etudiants.import.traiter') }}" enctype="multipart/form-data">
            @csrf
            <label for="fichier">Fichier CSV</label>
            <input id="fichier" name="fichier" type="file" accept=".csv,text/csv" required>
            <button class="bouton bouton-court">Lancer l'import</button>
        </form>
    </div>

    @if ($rapport)
        <div class="carte">
            <h2>Rapport d'import — {{ $rapport['crees'] }} créé(s), {{ $rapport['rejets'] }} rejeté(s)</h2>
            <table class="tableau">
                <thead><tr><th>Ligne</th><th>E-mail</th><th>Résultat</th><th>Motif</th></tr></thead>
                <tbody>
                @foreach ($rapport['lignes'] as $ligne)
                    <tr>
                        <td>{{ $ligne['ligne'] }}</td>
                        <td>{{ $ligne['email'] }}</td>
                        <td>{!! $ligne['resultat'] === 'Rejeté' ? '<span class="badge badge-rouge">Rejeté</span>' : '<span class="badge badge-vert">'.$ligne['resultat'].'</span>' !!}</td>
                        <td>{{ $ligne['motif'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
