@extends('layouts.app')

@section('titre', 'Mes offres — Passerelle')

@section('contenu')
    <h1>Mes offres</h1>
    <p class="sous-titre">Publication vers vos écoles partenaires ; chaque école modère indépendamment (RG-17).</p>

    <div class="carte">
        <table class="tableau">
            <thead><tr><th>Intitulé</th><th>Type</th><th>Période</th><th>Postes</th><th>Diffusions</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            @forelse ($offres as $offre)
                <tr>
                    <td>{{ $offre->intitule }}<br><small class="a-venir">{{ $offre->domaine->libelle }} · {{ $offre->niveau }} · {{ $offre->lieu }}</small></td>
                    <td>{{ $offre->type }}</td>
                    <td>{{ $offre->date_debut_prevue->format('d/m/Y') }} → {{ $offre->date_fin_prevue->format('d/m/Y') }}</td>
                    <td>{{ $offre->nb_postes }}</td>
                    <td>
                        @foreach ($offre->diffusions as $diffusion)
                            <div><small>{{ $diffusion->etablissement->nom }} :
                                <span class="badge badge-{{ ['validee' => 'vert', 'refusee' => 'rouge', 'soumise' => 'orange', 'caduque' => 'gris'][$diffusion->statut] }}">{{ $diffusion->statut }}</span>
                                @if ($diffusion->motif_refus)<em>({{ $diffusion->motif_refus }})</em>@endif
                            </small></div>
                        @endforeach
                    </td>
                    <td><span class="badge badge-{{ ['publiee' => 'vert', 'pourvue' => 'gris', 'retiree' => 'gris'][$offre->statut] }}">{{ $offre->statut }}</span></td>
                    <td>
                        @if ($offre->statut === 'publiee')
                            @if ($offre->modifiable())
                                <form method="POST" action="{{ route('entreprise.offres.retirer', $offre->id) }}">@csrf<button class="lien-sobre">retirer</button></form>
                            @endif
                            <form method="POST" action="{{ route('entreprise.offres.cloturer', $offre->id) }}">@csrf<button class="lien-sobre">clôturer (pourvue)</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune offre publiée pour l'instant.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="carte">
        <h2>Publier une offre</h2>
        @if ($partenariats->isEmpty())
            <p class="sous-titre">Aucun partenariat actif : demandez à une école de vous inviter (RG-16).</p>
        @else
        <form method="POST" action="{{ route('entreprise.offres.creer') }}">
            @csrf
            <div class="grille-form">
                <div><label for="intitule">Intitulé</label><input id="intitule" name="intitule" value="{{ old('intitule') }}" required></div>
                <div><label for="type">Type</label><select id="type" name="type"><option value="stage">stage</option><option value="alternance">alternance</option></select></div>
                <div><label for="niveau">Niveau visé</label><select id="niveau" name="niveau"><option>Bac+2</option><option>Bac+3</option><option>Bac+5</option></select></div>
                <div><label for="domaine_id">Domaine</label><select id="domaine_id" name="domaine_id">@foreach ($domaines as $d)<option value="{{ $d->id }}">{{ $d->libelle }}</option>@endforeach</select></div>
                <div><label for="lieu">Lieu</label><input id="lieu" name="lieu" value="{{ old('lieu') }}" required></div>
                <div><label for="nb_postes">Nombre de postes</label><input id="nb_postes" name="nb_postes" type="number" value="1" min="1" required></div>
                <div><label for="date_debut_prevue">Début prévu</label><input id="date_debut_prevue" name="date_debut_prevue" type="date" required></div>
                <div><label for="date_fin_prevue">Fin prévue</label><input id="date_fin_prevue" name="date_fin_prevue" type="date" required></div>
            </div>
            <label for="description">Description de la mission</label>
            <textarea id="description" name="description" rows="4" required>{{ old('description') }}</textarea>
            <label>Écoles destinataires (partenariats actifs)</label>
            <div class="cases">
                @foreach ($partenariats as $partenariat)
                    <label class="case"><input type="checkbox" name="etablissements[]" value="{{ $partenariat->etablissement_id }}"> {{ $partenariat->etablissement->nom }}</label>
                @endforeach
            </div>
            <button class="bouton bouton-court">Publier</button>
        </form>
        @endif
    </div>
@endsection
