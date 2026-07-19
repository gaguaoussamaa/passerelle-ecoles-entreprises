@extends('layouts.app')

@section('titre', 'Missions — Passerelle')

@section('contenu')
    <h1>Missions</h1>
    <p class="sous-titre">Désignez le tuteur en entreprise de chaque mission en montage (RG-28).</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @forelse ($missions as $mission)
        <div class="carte">
            <h2>{{ $mission->etudiant->prenom }} {{ $mission->etudiant->nom }} ({{ $mission->type }})</h2>
            <p class="sous-titre">{{ $mission->etudiant->promotion->formation->etablissement->nom }} ·
                {{ $mission->date_debut->format('d/m/Y') }} → {{ $mission->date_fin->format('d/m/Y') }}</p>
            <p>
                @switch($mission->statutCalcule())
                    @case('en_montage') <span class="badge badge-orange">en montage</span> @break
                    @case('en_contractualisation') <span class="badge badge-vert">en contractualisation</span> @break
                    @case('active') <span class="badge badge-vert">active</span> @break
                    @case('en_evaluation') <span class="badge badge-orange">en évaluation</span> @break
                    @case('annulee') <span class="badge badge-rouge">annulée</span> @break
                    @case('interrompue') <span class="badge badge-rouge">interrompue</span> @break
                    @default <span class="badge badge-gris">{{ $mission->libelleStatutCalcule() }}</span>
                @endswitch
                · Tuteur pédagogique : <strong>{{ $mission->tuteurPedagogique ? $mission->tuteurPedagogique->prenom.' '.$mission->tuteurPedagogique->nom : 'à désigner (par l\'école)' }}</strong>
                · Tuteur entreprise : <strong>{{ $mission->tuteurEntreprise ? $mission->tuteurEntreprise->prenom.' '.$mission->tuteurEntreprise->nom : 'à désigner' }}</strong>
            </p>

            @include('partials.convention', ['mission' => $mission, 'role' => 'entreprise'])
            @include('partials.evaluation', ['mission' => $mission, 'peutEvaluer' => true, 'criteres' => $criteres])
            @include('partials.signalements', ['mission' => $mission, 'peutTraiter' => false])

            @if ($mission->statut === 'contractualisee')
                <form method="POST" action="{{ route('entreprise.missions.signaler', $mission->id) }}">
                    @csrf
                    <label for="description_{{ $mission->id }}">Signaler une difficulté</label>
                    <textarea id="description_{{ $mission->id }}" name="description" rows="2" required minlength="10"
                        placeholder="Décrivez la difficulté rencontrée avec cette mission."></textarea>
                    <button class="bouton bouton-court bouton-danger">Signaler</button>
                </form>
            @endif

            @if ($mission->statut === 'en_montage' && ! $mission->tuteur_entreprise_id)
                <form method="POST" action="{{ route('entreprise.missions.tuteur', $mission->id) }}">
                    @csrf
                    <div class="grille-form">
                        <div><label for="prenom_{{ $mission->id }}">Prénom</label>
                            <input id="prenom_{{ $mission->id }}" name="prenom" required maxlength="80"></div>
                        <div><label for="nom_{{ $mission->id }}">Nom</label>
                            <input id="nom_{{ $mission->id }}" name="nom" required maxlength="80"></div>
                        <div><label for="email_{{ $mission->id }}">E-mail (optionnel)</label>
                            <input type="email" id="email_{{ $mission->id }}" name="email" maxlength="190"></div>
                    </div>
                    <button class="bouton bouton-court">Désigner le tuteur en entreprise</button>
                </form>
            @endif
        </div>
    @empty
        <div class="carte"><p>Aucune mission pour le moment.</p></div>
    @endforelse
@endsection
