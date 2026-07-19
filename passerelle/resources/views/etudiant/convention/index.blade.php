@extends('layouts.app')

@section('titre', 'Ma convention — Passerelle')

@section('contenu')
    <h1>Ma convention</h1>
    <p class="sous-titre">Validez à votre tour (circuit séquentiel), puis approuvez la version validée.</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    @forelse ($missions as $mission)
        <div class="carte">
            <h2>{{ $mission->entreprise->raison_sociale }} ({{ $mission->type }})</h2>
            <p class="sous-titre">{{ $mission->date_debut->format('d/m/Y') }} → {{ $mission->date_fin->format('d/m/Y') }}
                · mission {{ str_replace('_', ' ', $mission->statut) }}</p>
            @if ($mission->versionsConvention->isEmpty())
                <p>Aucune convention générée pour l'instant : votre responsable la produira
                    une fois les deux tuteurs désignés.</p>
            @else
                @include('partials.convention', ['mission' => $mission, 'role' => 'etudiant'])
            @endif
        </div>
    @empty
        <div class="carte"><p>Aucune mission : la convention apparaîtra ici après confirmation
            d'une candidature ou recevabilité d'une déclaration.</p></div>
    @endforelse
@endsection
