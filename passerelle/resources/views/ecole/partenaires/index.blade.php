@extends('layouts.app')

@section('titre', 'Partenaires — Passerelle')

@section('contenu')
    <h1>Entreprises partenaires</h1>
    <p class="sous-titre">L'invitation crée le compte entreprise ; le partenariat s'active avec lui (RG-47).</p>

    <div class="carte">
        <table class="tableau">
            <thead><tr><th>Entreprise</th><th>E-mail</th><th>Partenariat</th></tr></thead>
            <tbody>
            @forelse ($partenariats as $partenariat)
                <tr>
                    <td>{{ $partenariat->entreprise->raison_sociale }}</td>
                    <td>{{ $partenariat->entreprise->compte->email }}</td>
                    <td><span class="badge badge-{{ $partenariat->statut === 'actif' ? 'vert' : 'orange' }}">{{ str_replace('_', ' ', $partenariat->statut) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3">Aucun partenariat pour l'instant.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="carte">
        <h2>Inviter une entreprise</h2>
        <form method="POST" action="{{ route('ecole.partenaires.inviter') }}">
            @csrf
            <div class="grille-form">
                <div><label for="raison_sociale">Raison sociale</label><input id="raison_sociale" name="raison_sociale" required></div>
                <div><label for="email">E-mail du contact</label><input id="email" name="email" type="email" required></div>
            </div>
            <button class="bouton bouton-court">Inviter</button>
        </form>
    </div>
@endsection
