@extends('layouts.app')

@section('titre', 'Offres reçues — Passerelle')

@section('contenu')
    <h1>Offres reçues</h1>
    <p class="sous-titre">Validez (et ciblez les promotions — RG-19) ou refusez avec motif (RG-18) ; sans effet sur les autres écoles.</p>

    @forelse ($aModerer as $diffusion)
        <div class="carte">
            <h2>{{ $diffusion->offre->intitule }} — {{ $diffusion->offre->entreprise->raison_sociale }}</h2>
            <p class="sous-titre">
                {{ $diffusion->offre->type }} · {{ $diffusion->offre->niveau }} · {{ $diffusion->offre->domaine->libelle }} ·
                {{ $diffusion->offre->lieu }} ·
                {{ $diffusion->offre->date_debut_prevue->format('d/m/Y') }} → {{ $diffusion->offre->date_fin_prevue->format('d/m/Y') }} ·
                {{ $diffusion->offre->nb_postes }} poste(s)
            </p>
            <p>{{ $diffusion->offre->description }}</p>

            <form method="POST" action="{{ route('ecole.offres.valider', $diffusion->id) }}">
                @csrf
                <label>Promotions ciblées (obligatoire pour valider)</label>
                <div class="cases">
                    @foreach ($promotions as $promotion)
                        <label class="case"><input type="checkbox" name="promotions[]" value="{{ $promotion->id }}"> {{ $promotion->libelle }} — {{ $promotion->formation->intitule }}</label>
                    @endforeach
                </div>
                <button class="bouton bouton-court">Valider et publier aux promotions ciblées</button>
            </form>

            <form method="POST" action="{{ route('ecole.offres.refuser', $diffusion->id) }}">
                @csrf
                <label for="motif_refus_{{ $diffusion->id }}">Motif de refus (obligatoire)</label>
                <input id="motif_refus_{{ $diffusion->id }}" name="motif_refus" placeholder="ex. : mission hors référentiel de nos formations">
                <button class="bouton bouton-court bouton-danger">Refuser pour mon établissement</button>
            </form>
        </div>
    @empty
        <div class="carte"><p>Aucune offre en attente de modération.</p></div>
    @endforelse

    <div class="carte">
        <h2>Historique des diffusions traitées</h2>
        <table class="tableau">
            <thead><tr><th>Offre</th><th>Entreprise</th><th>Décision</th><th>Promotions ciblées / motif</th></tr></thead>
            <tbody>
            @forelse ($traitees as $diffusion)
                <tr>
                    <td>{{ $diffusion->offre->intitule }}</td>
                    <td>{{ $diffusion->offre->entreprise->raison_sociale }}</td>
                    <td><span class="badge badge-{{ ['validee' => 'vert', 'refusee' => 'rouge', 'caduque' => 'gris'][$diffusion->statut] }}">{{ $diffusion->statut }}</span></td>
                    <td>{{ $diffusion->statut === 'validee' ? $diffusion->offre->promotions->pluck('libelle')->join(', ') : ($diffusion->motif_refus ?? '—') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Aucune diffusion traitée.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection
