@extends('layouts.app')

@section('titre', 'Mon suivi — Passerelle')

@section('contenu')
    <h1>Mon suivi de mission</h1>
    <p class="sous-titre">Déposez vos rapports aux échéances ; signalez toute difficulté.</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @forelse ($missions as $mission)
        <div class="carte">
            <h2>{{ $mission->entreprise->raison_sociale }} ({{ $mission->type }})
                — <span class="badge badge-orange">{{ $mission->libelleStatutCalcule() }}</span></h2>
            <p class="sous-titre">{{ $mission->date_debut->format('d/m/Y') }} → {{ $mission->date_fin->format('d/m/Y') }}</p>

            @include('partials.jalons', ['mission' => $mission, 'peutDeposer' => true])
            @include('partials.signalements', ['mission' => $mission, 'peutTraiter' => false])

            @if ($mission->statut === 'contractualisee')
                <form method="POST" action="{{ route('etudiant.suivi.signaler', $mission->id) }}">
                    @csrf
                    <label for="description_{{ $mission->id }}">Signaler une difficulté</label>
                    <textarea id="description_{{ $mission->id }}" name="description" rows="2" required minlength="10"
                        placeholder="Décrivez la difficulté rencontrée (encadrement, missions confiées, conditions…)"></textarea>
                    <button class="bouton bouton-court bouton-danger">Signaler</button>
                </form>
            @endif
        </div>
    @empty
        <div class="carte"><p>Le suivi démarre à la contractualisation de votre mission
            (convention approuvée par les quatre parties).</p></div>
    @endforelse
@endsection
