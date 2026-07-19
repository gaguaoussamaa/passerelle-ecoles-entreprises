{{-- Évaluation de fin de mission ($mission, $peutEvaluer, $criteres si formulaire) — RG-41 --}}
@if ($mission->evaluation)
    <div class="convention">
        <h3>Évaluation de fin de mission</h3>
        <p class="sous-titre">Remplie par {{ $mission->evaluation->tuteurEntreprise->prenom }}
            {{ $mission->evaluation->tuteurEntreprise->nom }} (tuteur en entreprise)
            le {{ $mission->evaluation->created_at->format('d/m/Y') }}.</p>
        <table class="tableau">
            <thead><tr><th>Critère</th><th>Note / 5</th></tr></thead>
            <tbody>
            @foreach ($mission->evaluation->criteres as $critere)
                <tr><td>{{ $critere->libelle }}</td><td><strong>{{ $critere->pivot->note }}</strong> / 5</td></tr>
            @endforeach
            </tbody>
        </table>
        @if ($mission->evaluation->commentaire)
            <p><small>Commentaire : {{ $mission->evaluation->commentaire }}</small></p>
        @endif
    </div>
@elseif ($peutEvaluer && $mission->statutCalcule() === 'en_evaluation' && $mission->tuteur_entreprise_id)
    <div class="convention">
        <h3>Évaluation de fin de mission (grille standard)</h3>
        <p class="sous-titre">À remplir par le tuteur en entreprise :
            {{ $mission->tuteurEntreprise->prenom }} {{ $mission->tuteurEntreprise->nom }}.</p>
        <form method="POST" action="{{ route('entreprise.missions.evaluation', $mission->id) }}">
            @csrf
            <table class="tableau">
                <tbody>
                @foreach ($criteres as $critere)
                    <tr>
                        <td><label for="note_{{ $mission->id }}_{{ $critere->id }}">{{ $critere->libelle }}</label></td>
                        <td>
                            <select id="note_{{ $mission->id }}_{{ $critere->id }}" name="notes[{{ $critere->id }}]" required>
                                <option value="">— / 5</option>
                                @for ($n = 0; $n <= 5; $n++)<option value="{{ $n }}">{{ $n }}</option>@endfor
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <label for="commentaire_{{ $mission->id }}">Commentaire</label>
            <textarea id="commentaire_{{ $mission->id }}" name="commentaire" rows="3"
                placeholder="Appréciation générale, points forts, axes de progression."></textarea>
            <button class="bouton bouton-court">Enregistrer l'évaluation</button>
        </form>
    </div>
@endif
