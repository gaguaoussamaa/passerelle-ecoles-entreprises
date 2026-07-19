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
                @switch($mission->statut)
                    @case('en_montage') <span class="badge badge-orange">en montage</span> @break
                    @case('en_contractualisation') <span class="badge badge-vert">en contractualisation</span> @break
                    @case('annulee') <span class="badge badge-rouge">annulée</span> — {{ $mission->motif_arret }} @break
                    @default <span class="badge badge-gris">{{ str_replace('_', ' ', $mission->statut) }}</span>
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

            @if (in_array($mission->statut, ['en_montage', 'en_contractualisation']))
                <div class="filtres">
                    @if ($mission->declaration_id && $mission->entreprise->compte->mot_de_passe === null)
                        <form method="POST" action="{{ route('ecole.missions.invitation', $mission->id) }}">
                            @csrf <button class="bouton bouton-court">Renvoyer l'invitation entreprise</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('ecole.missions.annuler', $mission->id) }}">
                        @csrf
                        <input name="motif_arret" required minlength="5" placeholder="motif d'annulation">
                        <input type="date" name="date_effet_arret" required value="{{ now()->format('Y-m-d') }}">
                        <button class="bouton bouton-court bouton-danger">Annuler la mission</button>
                    </form>
                </div>
            @endif
        </div>
    @empty
        <div class="carte"><p>Aucune mission pour le moment : elles naissent d'une candidature confirmée
            ou d'une déclaration recevable.</p></div>
    @endforelse
@endsection
