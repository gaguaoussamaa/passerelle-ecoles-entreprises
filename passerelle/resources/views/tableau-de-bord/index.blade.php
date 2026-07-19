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
                @php($etab = $profil->etablissement)
                <div class="carte compteur"><span class="chiffre">{{ $etab->formations()->where('archivee', false)->count() }}</span> formation(s) active(s)</div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Promotion::whereHas('formation', fn ($q) => $q->where('etablissement_id', $etab->id))->where('archivee', false)->count() }}</span> promotion(s) active(s)</div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Etudiant::whereHas('promotion.formation', fn ($q) => $q->where('etablissement_id', $etab->id))->where('statut_scolarite', 'actif')->count() }}</span> étudiant(s) actif(s)
                    <span class="a-venir">{{ \App\Models\Etudiant::whereHas('promotion.formation', fn ($q) => $q->where('etablissement_id', $etab->id))->where('statut_scolarite', 'invite')->count() }} invitation(s) en attente</span></div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Diffusion::where('etablissement_id', $etab->id)->where('statut', 'soumise')->whereHas('offre', fn ($q) => $q->where('statut', 'publiee'))->count() }}</span> <a href="{{ route('ecole.offres') }}">offre(s) à modérer</a></div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Declaration::where('statut', 'soumise')->whereHas('etudiant.promotion.formation', fn ($q) => $q->where('etablissement_id', $etab->id))->count() }}</span> <a href="{{ route('ecole.declarations') }}">déclaration(s) à examiner</a></div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Mission::where('statut', 'en_montage')->whereHas('etudiant.promotion.formation', fn ($q) => $q->where('etablissement_id', $etab->id))->count() }}</span> <a href="{{ route('ecole.missions') }}">mission(s) en montage</a></div>
                @break
            @case('tuteur_pedagogique')
                <div class="carte compteur"><span class="chiffre">0</span> convention(s) à valider <span class="a-venir">(module conventions à venir)</span></div>
                <div class="carte compteur"><span class="chiffre">0</span> rapport(s) en retard <span class="a-venir">(module suivi à venir)</span></div>
                @break
            @case('etudiant')
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Offre::visiblesPar($profil)->count() }}</span> <a href="{{ route('etudiant.offres') }}">offre(s) visibles</a></div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Candidature::where('etudiant_id', $compte->id)->whereIn('statut', \App\Models\Candidature::ACTIFS)->count() }}</span> <a href="{{ route('etudiant.candidatures') }}">candidature(s) en cours</a>
                    @php($retenues = \App\Models\Candidature::where('etudiant_id', $compte->id)->where('statut', 'retenue')->count())
                    @if ($retenues) <span class="a-venir">{{ $retenues }} retenue(s) — à confirmer</span> @endif</div>
                <div class="carte compteur"><span class="chiffre">0</span> action(s) sur ma convention <span class="a-venir">(module conventions à venir)</span></div>
                @break
            @case('entreprise')
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Offre::where('entreprise_id', $compte->id)->where('statut', 'publiee')->count() }}</span> <a href="{{ route('entreprise.offres') }}">offre(s) publiée(s)</a></div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Candidature::whereHas('offre', fn ($q) => $q->where('entreprise_id', $compte->id))->whereIn('statut', \App\Models\Candidature::EN_EXAMEN)->count() }}</span> <a href="{{ route('entreprise.candidatures') }}">candidature(s) à traiter</a></div>
                <div class="carte compteur"><span class="chiffre">0</span> convention(s) en attente <span class="a-venir">(module conventions à venir)</span></div>
                @break
            @case('super_admin')
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Etablissement::count() }}</span> établissement(s) actifs</div>
                <div class="carte compteur"><span class="chiffre">{{ \App\Models\Compte::count() }}</span> compte(s) sur la plateforme</div>
                @break
        @endswitch
    </div>
@endsection
