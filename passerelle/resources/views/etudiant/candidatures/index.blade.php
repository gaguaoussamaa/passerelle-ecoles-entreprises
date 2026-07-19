@extends('layouts.app')

@section('titre', 'Mes candidatures — Passerelle')

@section('contenu')
    <h1>Mes candidatures</h1>
    <p class="sous-titre">Votre CV de profil et le suivi de vos candidatures.</p>

    @if (session('succes'))
        <div class="message message-succes">{{ session('succes') }}</div>
    @endif
    @if ($errors->any())
        <div class="message message-erreur">{{ $errors->first() }}</div>
    @endif

    <div class="carte">
        <h2>Mon CV</h2>
        @if ($etudiant->cv_profil)
            <p>CV enregistré — <a href="{{ route('etudiant.candidatures.cv') }}">télécharger</a>.
                <small>Le remplacer ne modifie pas les candidatures déjà émises (le CV y est figé).</small></p>
        @else
            <p>Aucun CV : déposez-en un (PDF, 2 Mo max.) pour pouvoir candidater.</p>
        @endif
        <form method="POST" action="{{ route('etudiant.candidatures.cv.deposer') }}" enctype="multipart/form-data">
            @csrf
            <input type="file" name="cv" accept="application/pdf" required>
            <button class="bouton bouton-court">{{ $etudiant->cv_profil ? 'Remplacer le CV' : 'Déposer le CV' }}</button>
        </form>
    </div>

    @forelse ($candidatures as $candidature)
        <div class="carte">
            <h2>{{ $candidature->offre->intitule }}</h2>
            <p class="sous-titre">{{ $candidature->offre->entreprise->raison_sociale }}
                · déposée le {{ $candidature->created_at->format('d/m/Y') }}
                · <a href="{{ route('etudiant.candidatures.cv.depose', $candidature->id) }}">CV déposé</a></p>
            <p>
                @switch($candidature->statut)
                    @case('confirmee') <span class="badge badge-vert">confirmée</span>
                        @if ($candidature->mission) — mission créée ({{ $candidature->mission->statut === 'en_montage' ? 'en montage' : $candidature->mission->statut }}) @endif
                        @break
                    @case('retenue') <span class="badge badge-orange">retenue — à vous de décider</span> @break
                    @case('refusee') @case('declinee') @case('retiree')
                        <span class="badge badge-gris">{{ $candidature->libelleStatut() }}</span> @break
                    @default <span class="badge badge-orange">{{ $candidature->libelleStatut() }}</span>
                @endswitch
            </p>
            @if ($candidature->statut === 'retenue')
                <div class="filtres">
                    <form method="POST" action="{{ route('etudiant.candidatures.confirmer', $candidature->id) }}">
                        @csrf <button class="bouton bouton-court">Confirmer mon engagement</button>
                    </form>
                    <form method="POST" action="{{ route('etudiant.candidatures.decliner', $candidature->id) }}">
                        @csrf <button class="bouton bouton-court bouton-danger">Décliner</button>
                    </form>
                </div>
            @elseif ($candidature->retirable())
                <form method="POST" action="{{ route('etudiant.candidatures.retirer', $candidature->id) }}">
                    @csrf <button class="lien-sobre">Retirer ma candidature</button>
                </form>
            @endif
        </div>
    @empty
        <div class="carte"><p>Aucune candidature — consultez les <a href="{{ route('etudiant.offres') }}">offres de votre promotion</a>.</p></div>
    @endforelse
@endsection
