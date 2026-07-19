@extends('layouts.app')

@section('titre', 'Tableau de bord — Passerelle')

@section('contenu')
    <h1>Tableau de bord</h1>
    <p class="sous-titre">
        @switch($compte->role)
            @case('super_admin') Supervision de la plateforme. @break
            @case('responsable') {{ $profil?->etablissement?->nom }} — vos actions à traiter. @break
            @case('tuteur_pedagogique') {{ $profil?->etablissement?->nom }} — suivi de vos étudiants. @break
            @case('etudiant') {{ $profil?->prenom }} {{ $profil?->nom }} — votre parcours. @break
            @case('entreprise') {{ $profil?->raison_sociale }} — vos recrutements et missions. @break
        @endswitch
    </p>

    <div class="grille-cartes">
        @switch($compte->role)
            @case('responsable')
                <div class="carte compteur"><span class="chiffre">0</span> offre(s) à modérer <span class="a-venir">(module offres à venir)</span></div>
                <div class="carte compteur"><span class="chiffre">0</span> déclaration(s) à examiner <span class="a-venir">(module missions à venir)</span></div>
                <div class="carte compteur"><span class="chiffre">0</span> convention(s) en attente <span class="a-venir">(module conventions à venir)</span></div>
                @break
            @case('tuteur_pedagogique')
                <div class="carte compteur"><span class="chiffre">0</span> convention(s) à valider <span class="a-venir">(module conventions à venir)</span></div>
                <div class="carte compteur"><span class="chiffre">0</span> rapport(s) en retard <span class="a-venir">(module suivi à venir)</span></div>
                @break
            @case('etudiant')
                <div class="carte compteur"><span class="chiffre">0</span> offre(s) visibles <span class="a-venir">(module offres à venir)</span></div>
                <div class="carte compteur"><span class="chiffre">0</span> action(s) sur ma convention <span class="a-venir">(module conventions à venir)</span></div>
                @break
            @case('entreprise')
                <div class="carte compteur"><span class="chiffre">0</span> candidature(s) reçues <span class="a-venir">(module candidatures à venir)</span></div>
                <div class="carte compteur"><span class="chiffre">0</span> convention(s) en attente <span class="a-venir">(module conventions à venir)</span></div>
                @break
            @case('super_admin')
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Etablissement::count() }}</span> établissement(s) actifs</div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Compte::count() }}</span> compte(s) sur la plateforme</div>
                @break
        @endswitch
    </div>
@endsection
