@extends('layouts.app')

@section('titre', 'Connexion — Passerelle')

@section('contenu')
    <div class="carte carte-etroite">
        <h1>Connexion</h1>
        <p class="sous-titre">Accédez à votre espace selon votre rôle : établissement, tuteur, étudiant ou entreprise.</p>

        <form method="POST" action="{{ route('connexion.soumettre') }}">
            @csrf
            <label for="email">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

            <label for="mot_de_passe">Mot de passe</label>
            <input id="mot_de_passe" name="mot_de_passe" type="password" required>

            <button type="submit" class="bouton">Se connecter</button>
        </form>
    </div>
@endsection
