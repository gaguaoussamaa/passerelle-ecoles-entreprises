@extends('layouts.app')

@section('titre', 'Déclarations — Passerelle')

@section('contenu')
    <h1>Déclarations hors plateforme</h1>
    <p class="sous-titre">Examinez la recevabilité avant toute sollicitation de l'entreprise (RG-27).</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    <h2>À examiner ({{ $aExaminer->count() }})</h2>
    @forelse ($aExaminer as $declaration)
        <div class="carte">
            <h2>{{ $declaration->etudiant->prenom }} {{ $declaration->etudiant->nom }}
                — {{ $declaration->entreprise_saisie }} ({{ $declaration->type_mission }})</h2>
            <p class="sous-titre">{{ $declaration->etudiant->promotion->formation->intitule }} ·
                {{ $declaration->date_debut_prevue->format('d/m/Y') }} → {{ $declaration->date_fin_prevue->format('d/m/Y') }} ·
                contact : {{ $declaration->contact_nom }} &lt;{{ $declaration->contact_email }}&gt;
                @if ($declaration->siret_saisi) · SIRET {{ $declaration->siret_saisi }} @endif</p>
            <p><small>{{ $declaration->description }}</small></p>
            <div class="filtres">
                <form method="POST" action="{{ route('ecole.declarations.valider', $declaration->id) }}">
                    @csrf <button class="bouton bouton-court">Recevable — créer la mission</button>
                </form>
                <form method="POST" action="{{ route('ecole.declarations.refuser', $declaration->id) }}">
                    @csrf
                    <input name="motif_refus" required minlength="5" placeholder="motif du refus (obligatoire)">
                    <button class="bouton bouton-court bouton-danger">Refuser</button>
                </form>
            </div>
        </div>
    @empty
        <div class="carte"><p>Aucune déclaration en attente d'examen.</p></div>
    @endforelse

    @if ($traitees->isNotEmpty())
        <h2>Traitées récemment</h2>
        <div class="carte">
            <table class="tableau">
                <thead><tr><th>Étudiant</th><th>Entreprise</th><th>Statut</th><th>Détail</th></tr></thead>
                <tbody>
                @foreach ($traitees as $declaration)
                    <tr>
                        <td>{{ $declaration->etudiant->prenom }} {{ $declaration->etudiant->nom }}</td>
                        <td>{{ $declaration->entreprise_saisie }}</td>
                        <td><span class="badge {{ ['recevable' => 'badge-vert', 'refusee' => 'badge-rouge'][$declaration->statut] ?? 'badge-gris' }}">{{ $declaration->libelleStatut() }}</span></td>
                        <td><small>{{ $declaration->statut === 'refusee' ? $declaration->motif_refus : ($declaration->mission ? 'mission '.str_replace('_', ' ', $declaration->mission->statut) : '') }}</small></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
