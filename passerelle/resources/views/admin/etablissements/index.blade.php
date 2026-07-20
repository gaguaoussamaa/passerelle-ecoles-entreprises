@extends('layouts.app')

@section('titre', 'Établissements — Passerelle')

@section('contenu')
    <h1>Établissements clients</h1>
    <p class="sous-titre">Créez l'espace, attribuez le plan d'abonnement (RG-44) et invitez le premier responsable.</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    <div class="carte">
        <h2>Nouvel établissement</h2>
        <form method="POST" action="{{ route('admin.etablissements.creer') }}">
            @csrf
            <div class="grille-form">
                <div><label for="nom">Nom</label>
                    <input id="nom" name="nom" required maxlength="150" value="{{ old('nom') }}"></div>
                <div><label for="siret">SIRET</label>
                    <input id="siret" name="siret" required pattern="[0-9]{14}" maxlength="14" value="{{ old('siret') }}"></div>
                <div><label for="ville">Ville</label>
                    <input id="ville" name="ville" required maxlength="100" value="{{ old('ville') }}"></div>
            </div>
            <div class="grille-form">
                <div><label for="plan_abonnement">Plan d'abonnement</label>
                    <select id="plan_abonnement" name="plan_abonnement" required>
                        @foreach ($plans as $plan)
                            <option value="{{ $plan }}" @selected(old('plan_abonnement') === $plan)>{{ ucfirst($plan) }}</option>
                        @endforeach
                    </select></div>
                <div><label for="debut_abonnement">Début</label>
                    <input type="date" id="debut_abonnement" name="debut_abonnement" required value="{{ old('debut_abonnement') }}"></div>
                <div><label for="fin_abonnement">Fin</label>
                    <input type="date" id="fin_abonnement" name="fin_abonnement" required value="{{ old('fin_abonnement') }}"></div>
            </div>
            <div class="grille-form">
                <div><label for="responsable_prenom">Responsable — prénom</label>
                    <input id="responsable_prenom" name="responsable_prenom" required maxlength="80" value="{{ old('responsable_prenom') }}"></div>
                <div><label for="responsable_nom">Responsable — nom</label>
                    <input id="responsable_nom" name="responsable_nom" required maxlength="80" value="{{ old('responsable_nom') }}"></div>
                <div><label for="responsable_email">Responsable — e-mail</label>
                    <input type="email" id="responsable_email" name="responsable_email" required maxlength="190" value="{{ old('responsable_email') }}"></div>
            </div>
            <button class="bouton bouton-court">Créer l'espace et inviter le responsable</button>
        </form>
    </div>

    <div class="carte">
        <h2>Espaces existants</h2>
        <table class="tableau">
            <thead><tr><th>Établissement</th><th>Ville</th><th>Plan</th><th>Abonnement</th><th>Responsables</th></tr></thead>
            <tbody>
            @foreach ($etablissements as $etablissement)
                <tr>
                    <td>{{ $etablissement->nom }}</td>
                    <td>{{ $etablissement->ville }}</td>
                    <td><span class="badge badge-vert">{{ $etablissement->plan_abonnement }}</span></td>
                    <td>{{ $etablissement->debut_abonnement->format('d/m/Y') }} → {{ $etablissement->fin_abonnement->format('d/m/Y') }}</td>
                    <td>{{ $etablissement->responsables_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endsection
