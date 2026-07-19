@extends('layouts.app')

@section('titre', 'Candidatures — Passerelle')

@section('contenu')
    <h1>Candidatures reçues</h1>
    <p class="sous-titre">Faites évoluer chaque dossier : le candidat est notifié à chaque changement.</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @forelse ($offres as $offre)
        <div class="carte">
            <h2>{{ $offre->intitule }} <small>({{ $offre->statut }})</small></h2>
            <table class="tableau">
                <thead><tr><th>Candidat</th><th>École / promotion</th><th>Message</th><th>CV</th><th>Statut</th><th>Action</th></tr></thead>
                <tbody>
                @foreach ($offre->candidatures as $candidature)
                    <tr>
                        <td>{{ $candidature->etudiant->prenom }} {{ $candidature->etudiant->nom }}</td>
                        <td>{{ $candidature->etudiant->promotion->formation->etablissement->nom }}<br>
                            <small>{{ $candidature->etudiant->promotion->libelle }}</small></td>
                        <td><small>{{ $candidature->message }}</small></td>
                        <td><a href="{{ route('entreprise.candidatures.cv', $candidature->id) }}">CV</a></td>
                        <td>
                            @switch($candidature->statut)
                                @case('confirmee') <span class="badge badge-vert">confirmée</span> @break
                                @case('retenue') <span class="badge badge-orange">retenue — attente du candidat</span> @break
                                @case('refusee') @case('declinee') @case('retiree')
                                    <span class="badge badge-gris">{{ $candidature->libelleStatut() }}</span> @break
                                @default <span class="badge badge-orange">{{ $candidature->libelleStatut() }}</span>
                            @endswitch
                        </td>
                        <td>
                            @if ($candidature->retirable())
                                <form method="POST" action="{{ route('entreprise.candidatures.statut', $candidature->id) }}">
                                    @csrf
                                    <select name="statut" required>
                                        <option value="">— statut —</option>
                                        @if ($candidature->statut !== 'preselectionnee')<option value="preselectionnee">présélectionnée</option>@endif
                                        @if ($candidature->statut !== 'entretien')<option value="entretien">entretien</option>@endif
                                        <option value="retenue">retenue</option>
                                        <option value="refusee">refusée</option>
                                    </select>
                                    <button class="bouton bouton-court">Appliquer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <div class="carte"><p>Aucune candidature reçue pour le moment.</p></div>
    @endforelse
@endsection
