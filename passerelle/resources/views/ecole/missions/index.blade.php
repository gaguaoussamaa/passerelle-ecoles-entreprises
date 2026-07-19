@extends('layouts.app')

@section('titre', 'Missions — Passerelle')

@section('contenu')
    <h1>Missions</h1>
    <p class="sous-titre">Désignez le tuteur pédagogique ; la mission passe « en contractualisation »
        quand les deux tuteurs sont connus (RG-28).</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @forelse ($missions as $mission)
        <div class="carte">
            <h2>{{ $mission->etudiant->prenom }} {{ $mission->etudiant->nom }}
                — {{ $mission->entreprise->raison_sociale }} ({{ $mission->type }})</h2>
            <p class="sous-titre">
                {{ $mission->etudiant->promotion->formation->intitule }} ·
                {{ $mission->date_debut->format('d/m/Y') }} → {{ $mission->date_fin->format('d/m/Y') }} ·
                origine : {{ $mission->candidature_id ? 'candidature (offre '.($mission->candidature->offre->intitule ?? '').')' : 'déclaration hors plateforme' }}
            </p>
            <p>
                @switch($mission->statutCalcule())
                    @case('en_montage') <span class="badge badge-orange">en montage</span> @break
                    @case('en_contractualisation') <span class="badge badge-vert">en contractualisation</span> @break
                    @case('active') <span class="badge badge-vert">active</span> @break
                    @case('en_evaluation') <span class="badge badge-orange">en évaluation</span> @break
                    @case('annulee') <span class="badge badge-rouge">annulée</span> — {{ $mission->motif_arret }} @break
                    @case('interrompue') <span class="badge badge-rouge">interrompue</span> — {{ $mission->motif_arret }} @break
                    @default <span class="badge badge-gris">{{ $mission->libelleStatutCalcule() }}</span>
                @endswitch
                · Tuteur pédagogique : <strong>{{ $mission->tuteurPedagogique ? $mission->tuteurPedagogique->prenom.' '.$mission->tuteurPedagogique->nom : 'à désigner' }}</strong>
                · Tuteur entreprise : <strong>{{ $mission->tuteurEntreprise ? $mission->tuteurEntreprise->prenom.' '.$mission->tuteurEntreprise->nom : 'à désigner (par l\'entreprise)' }}</strong>
                @if ($mission->declaration_id && $mission->entreprise->compte->mot_de_passe === null)
                    · <span class="badge badge-orange">entreprise non activée</span>
                @endif
            </p>

            @if ($mission->statut === 'en_montage' && ! $mission->tuteur_pedagogique_id)
                @php($rattaches = $enseignants[$mission->etudiant->promotion->formation_id] ?? collect())
                @if ($rattaches->isEmpty())
                    <p class="message message-erreur">Aucun enseignant rattaché à la formation
                        « {{ $mission->etudiant->promotion->formation->intitule }} » : rattachez-en un depuis la page
                        <a href="{{ route('ecole.tuteurs') }}">Tuteurs</a> avant de désigner (RG-28).</p>
                @else
                    <form method="POST" action="{{ route('ecole.missions.tuteur', $mission->id) }}" class="filtres">
                        @csrf
                        <select name="tuteur_id" required>
                            <option value="">— tuteur pédagogique —</option>
                            @foreach ($rattaches as $tuteur)
                                <option value="{{ $tuteur->compte_id }}">{{ $tuteur->prenom }} {{ $tuteur->nom }}</option>
                            @endforeach
                        </select>
                        <button class="bouton bouton-court">Désigner</button>
                    </form>
                @endif
            @endif

            @if ($mission->statut === 'en_contractualisation'
                && $mission->versionsConvention->whereIn('statut', \App\Models\VersionConvention::EN_CIRCULATION)->isEmpty())
                <form method="POST" action="{{ route('ecole.missions.convention', $mission->id) }}">
                    @csrf <button class="bouton bouton-court">Générer la convention
                        ({{ $mission->type === 'stage' ? 'convention de stage' : 'dossier d\'alternance' }})
                        — v{{ ($mission->versionsConvention->max('numero') ?? 0) + 1 }}</button>
                </form>
            @endif
            @include('partials.convention', ['mission' => $mission, 'role' => 'responsable'])

            @if ($mission->statut === 'contractualisee')
                <h3>Suivi</h3>
                @include('partials.jalons', ['mission' => $mission, 'peutDeposer' => false])
            @endif
            @include('partials.signalements', ['mission' => $mission, 'peutTraiter' => true])

            @if (in_array($mission->statut, ['en_montage', 'en_contractualisation', 'contractualisee']))
                <div class="filtres">
                    @if ($mission->declaration_id && $mission->entreprise->compte->mot_de_passe === null)
                        <form method="POST" action="{{ route('ecole.missions.invitation', $mission->id) }}">
                            @csrf <button class="bouton bouton-court">Renvoyer l'invitation entreprise</button>
                        </form>
                    @endif
                    @php($interruption = in_array($mission->statutCalcule(), ['active', 'en_evaluation']))
                    <form method="POST" action="{{ route('ecole.missions.annuler', $mission->id) }}">
                        @csrf
                        <input name="motif_arret" required minlength="5" placeholder="motif ({{ $interruption ? 'interruption' : 'annulation' }})">
                        <input type="date" name="date_effet_arret" required value="{{ now()->format('Y-m-d') }}">
                        <button class="bouton bouton-court bouton-danger">{{ $interruption ? 'Interrompre la mission' : 'Annuler la mission' }}</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <div class="carte"><p>Aucune mission pour le moment : elles naissent d'une candidature confirmée
            ou d'une déclaration recevable.</p></div>
    @endforelse
@endsection
