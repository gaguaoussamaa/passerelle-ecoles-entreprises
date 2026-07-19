@extends('layouts.app')

@section('titre', 'Activation du compte — Passerelle')

@section('contenu')
    <div class="carte carte-etroite">
        <h1>Activation de votre compte</h1>

        @if (! $invitation)
            <div class="message message-erreur">
                Ce lien d'activation est invalide, expiré ou déjà utilisé.
                Demandez un renvoi d'invitation à votre établissement ou à votre contact Passerelle.
            </div>
        @else
            <p class="sous-titre">
                Compte : <strong>{{ $invitation->compte->email }}</strong><br>
                Choisissez votre mot de passe (10 caractères minimum) pour activer l'accès.
            </p>

            <form method="POST" action="{{ route('activation.soumettre') }}">
                @csrf
                <input type="hidden" name="jeton" value="{{ $jeton }}">

                <label for="mot_de_passe">Mot de passe</label>
                <input id="mot_de_passe" name="mot_de_passe" type="password" required minlength="10">

                <label for="mot_de_passe_confirmation">Confirmation du mot de passe</label>
                <input id="mot_de_passe_confirmation" name="mot_de_passe_confirmation" type="password" required minlength="10">

                <button type="submit" class="bouton">Activer mon compte</button>
            </form>
        @endif
    </div>
@endsection
