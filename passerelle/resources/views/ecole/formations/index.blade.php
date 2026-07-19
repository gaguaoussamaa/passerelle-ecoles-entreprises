@extends('layouts.app')

@section('titre', 'Formations — Passerelle')

@section('contenu')
    <h1>Formations</h1>
    <p class="sous-titre">Cursus proposés par votre établissement (RG-09 : au moins un domaine par formation).</p>

    <div class="carte">
        <table class="tableau">
            <thead><tr><th>Intitulé</th><th>Niveau</th><th>Type</th><th>Domaines</th><th>Promotions</th><th>État</th><th></th></tr></thead>
            <tbody>
            @forelse ($formations as $formation)
                <tr class="{{ $formation->archivee ? 'ligne-archivee' : '' }}">
                    <td>{{ $formation->intitule }}</td>
                    <td>{{ $formation->niveau }}</td>
                    <td>{{ str_replace('_', ' ', $formation->type_mission) }}</td>
                    <td>{{ $formation->domaines->pluck('libelle')->join(', ') }}</td>
                    <td>{{ $formation->promotions_count }}</td>
                    <td>{!! $formation->archivee ? '<span class="badge badge-gris">archivée</span>' : '<span class="badge badge-vert">active</span>' !!}</td>
                    <td>
                        <form method="POST" action="{{ route('ecole.formations.archiver', $formation->id) }}">
                            @csrf
                            <button class="lien-sobre">{{ $formation->archivee ? 'réactiver' : 'archiver' }}</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">Aucune formation pour l'instant.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="carte">
        <h2>Créer une formation</h2>
        <form method="POST" action="{{ route('ecole.formations.creer') }}">
            @csrf
            <div class="grille-form">
                <div>
                    <label for="intitule">Intitulé</label>
                    <input id="intitule" name="intitule" value="{{ old('intitule') }}" required>
                </div>
                <div>
                    <label for="niveau">Niveau</label>
                    <select id="niveau" name="niveau" required>
                        @foreach (['Bac+2', 'Bac+3', 'Bac+5'] as $n)
                            <option @selected(old('niveau') === $n)>{{ $n }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="type_mission">Type de mission</label>
                    <select id="type_mission" name="type_mission" required>
                        <option value="stage" @selected(old('type_mission') === 'stage')>stage</option>
                        <option value="alternance" @selected(old('type_mission') === 'alternance')>alternance</option>
                        <option value="les_deux" @selected(old('type_mission') === 'les_deux')>les deux</option>
                    </select>
                </div>
            </div>
            <label>Domaines (au moins un)</label>
            <div class="cases">
                @foreach ($domaines as $domaine)
                    <label class="case"><input type="checkbox" name="domaines[]" value="{{ $domaine->id }}"> {{ $domaine->libelle }}</label>
                @endforeach
            </div>
            <button class="bouton bouton-court">Créer la formation</button>
        </form>
    </div>
@endsection
