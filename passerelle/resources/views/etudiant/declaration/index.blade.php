@extends('layouts.app')

@section('titre', 'Ma déclaration — Passerelle')

@section('contenu')
    <h1>Déclarer une mission trouvée hors plateforme</h1>
    <p class="sous-titre">Votre responsable examinera la recevabilité avant tout contact avec l'entreprise.</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @if ($declaration)
        <div class="carte">
            <h2>{{ $declaration->entreprise_saisie }} — {{ $declaration->type_mission }}</h2>
            <p class="sous-titre">{{ $declaration->date_debut_prevue->format('d/m/Y') }} →
                {{ $declaration->date_fin_prevue->format('d/m/Y') }} ·
                contact : {{ $declaration->contact_nom }} ({{ $declaration->contact_email }})</p>
            <p>
                @switch($declaration->statut)
                    @case('recevable') <span class="badge badge-vert">recevable — mission créée</span>
                        @if ($declaration->mission) <small>(statut mission : {{ str_replace('_', ' ', $declaration->mission->statut) }})</small> @endif
                        @break
                    @case('soumise') <span class="badge badge-orange">soumise — en attente d'examen</span> @break
                    @case('refusee') <span class="badge badge-rouge">refusée</span> — {{ $declaration->motif_refus }} @break
                    @default <span class="badge badge-gris">{{ $declaration->libelleStatut() }}</span>
                @endswitch
            </p>
            @if (in_array($declaration->statut, \App\Models\Declaration::EN_COURS))
                <form method="POST" action="{{ route('etudiant.declaration.abandonner') }}">
                    @csrf <button class="lien-sobre">Abandonner cette déclaration</button>
                </form>
            @endif
        </div>
    @endif

    @if ($missionEnCours)
        <div class="carte"><p>Une mission est en cours ({{ str_replace('_', ' ', $missionEnCours->statut) }}) :
            aucune nouvelle déclaration n'est possible.</p></div>
    @elseif (! $declaration || ! in_array($declaration->statut, ['soumise', 'recevable']))
        <div class="carte">
            <h2>{{ $declaration && $declaration->statut === 'refusee' ? 'Corriger et soumettre à nouveau' : 'Nouvelle déclaration' }}</h2>
            @php($d = $declaration && $declaration->statut === 'refusee' ? $declaration : null)
            <form method="POST" action="{{ route('etudiant.declaration.soumettre') }}">
                @csrf
                <div class="grille-form">
                    <div><label for="type_mission">Type</label>
                        <select id="type_mission" name="type_mission" required>
                            <option value="stage" @selected(old('type_mission', $d?->type_mission) === 'stage')>Stage</option>
                            <option value="alternance" @selected(old('type_mission', $d?->type_mission) === 'alternance')>Alternance</option>
                        </select></div>
                    <div><label for="date_debut_prevue">Début prévu</label>
                        <input type="date" id="date_debut_prevue" name="date_debut_prevue" required value="{{ old('date_debut_prevue', $d?->date_debut_prevue?->format('Y-m-d')) }}"></div>
                    <div><label for="date_fin_prevue">Fin prévue</label>
                        <input type="date" id="date_fin_prevue" name="date_fin_prevue" required value="{{ old('date_fin_prevue', $d?->date_fin_prevue?->format('Y-m-d')) }}"></div>
                </div>
                <div class="grille-form">
                    <div><label for="entreprise_saisie">Entreprise (raison sociale)</label>
                        <input id="entreprise_saisie" name="entreprise_saisie" required maxlength="150" value="{{ old('entreprise_saisie', $d?->entreprise_saisie) }}"></div>
                    <div><label for="siret_saisi">SIRET (optionnel)</label>
                        <input id="siret_saisi" name="siret_saisi" pattern="[0-9]{14}" maxlength="14" value="{{ old('siret_saisi', $d?->siret_saisi) }}"></div>
                </div>
                <div class="grille-form">
                    <div><label for="contact_nom">Contact (nom)</label>
                        <input id="contact_nom" name="contact_nom" required maxlength="120" value="{{ old('contact_nom', $d?->contact_nom) }}"></div>
                    <div><label for="contact_email">Contact (e-mail)</label>
                        <input type="email" id="contact_email" name="contact_email" required maxlength="190" value="{{ old('contact_email', $d?->contact_email) }}"></div>
                </div>
                <label for="description">Description de la mission</label>
                <textarea id="description" name="description" rows="4" required minlength="20"
                    placeholder="Contenu de la mission, technologies, encadrement prévu…">{{ old('description', $d?->description) }}</textarea>
                <button class="bouton bouton-court">{{ $d ? 'Soumettre la correction' : 'Soumettre ma déclaration' }}</button>
            </form>
        </div>
    @endif
@endsection
