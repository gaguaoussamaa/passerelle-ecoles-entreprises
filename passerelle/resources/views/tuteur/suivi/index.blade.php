@extends('layouts.app')

@section('titre', 'Suivi — Passerelle')

@section('contenu')
    <h1>Suivi de mes étudiants</h1>
    <p class="sous-titre">Rapports déposés, jalons en retard (RG-39) et signalements à traiter (RG-40).</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @forelse ($missions as $mission)
        <div class="carte">
            <h2>{{ $mission->etudiant->prenom }} {{ $mission->etudiant->nom }}
                — {{ $mission->entreprise->raison_sociale }} ({{ $mission->type }})
                — <span class="badge badge-orange">{{ $mission->libelleStatutCalcule() }}</span></h2>
            <p class="sous-titre">{{ $mission->date_debut->format('d/m/Y') }} → {{ $mission->date_fin->format('d/m/Y') }}
                @php($retards = $mission->jalons->filter(fn ($j) => $j->etat() === 'en_retard')->count())
                @if ($retards) · <span class="badge badge-rouge">{{ $retards }} jalon(s) en retard</span> @endif</p>

            @include('partials.jalons', ['mission' => $mission, 'peutDeposer' => false])
            @include('partials.signalements', ['mission' => $mission, 'peutTraiter' => true])
        </div>
    @empty
        <div class="carte"><p>Aucune mission contractualisée à suivre pour le moment.</p></div>
    @endforelse
@endsection
