{{-- Bloc convention d'une mission, vu par une partie ($mission, $role) --}}
@php($versions = $mission->versionsConvention->sortByDesc('numero'))
@if ($versions->isNotEmpty())
    <div class="convention">
        <h3>Convention</h3>
        @foreach ($versions as $version)
            <p>
                <strong>v{{ $version->numero }}</strong> —
                <span class="badge {{ ['approuvee' => 'badge-vert', 'refusee_correction' => 'badge-rouge', 'annulee' => 'badge-rouge', 'remplacee' => 'badge-gris'][$version->statut] ?? 'badge-orange' }}">{{ $version->libelleStatut() }}</span>
                · générée le {{ $version->created_at->format('d/m/Y H:i') }}
                · <a href="{{ route('conventions.pdf', $version->id) }}">PDF</a>
                <small>· empreinte {{ substr($version->empreinte, 0, 12) }}…</small>
            </p>
            @if ($version->statut === 'refusee_correction' && ($refus = $version->actions->where('type', 'refus')->last()))
                <p><small>Refus ({{ str_replace('_', ' ', $refus->role_partie) }}) : {{ $refus->motif }}</small></p>
            @endif

            @if ($loop->first)
                @php($attendu = $version->actionAttendueDe($role))
                @if ($attendu === 'valider')
                    <div class="filtres">
                        <form method="POST" action="{{ route('conventions.valider', $version->id) }}">
                            @csrf <button class="bouton bouton-court">Valider (à mon tour dans le circuit)</button>
                        </form>
                        <form method="POST" action="{{ route('conventions.refuser', $version->id) }}">
                            @csrf
                            <input name="motif" required minlength="5" placeholder="motif du refus">
                            <button class="bouton bouton-court bouton-danger">Refuser</button>
                        </form>
                    </div>
                @elseif ($attendu === 'approuver')
                    <div class="filtres">
                        <form method="POST" action="{{ route('conventions.approuver', $version->id) }}">
                            @csrf <button class="bouton bouton-court">Approuver (ordre libre)</button>
                        </form>
                        <form method="POST" action="{{ route('conventions.refuser', $version->id) }}">
                            @csrf
                            <input name="motif" required minlength="5" placeholder="motif du refus">
                            <button class="bouton bouton-court bouton-danger">Refuser</button>
                        </form>
                    </div>
                @elseif (in_array($version->statut, ['emise', 'en_validation']))
                    <p><small>En attente de : {{ str_replace('_', ' ', $version->prochainValideur()) }}
                        (circuit étudiant → entreprise → tuteur → responsable).</small></p>
                @elseif (in_array($version->statut, ['validee', 'en_approbation']))
                    <p><small>Approbations : {{ $version->approbationsEffectuees()->map(fn ($r) => str_replace('_', ' ', $r))->join(', ') ?: 'aucune encore' }}.</small></p>
                @endif
                @if ($role === 'responsable' && $version->statut === 'approuvee')
                    <form method="POST" action="{{ route('conventions.annuler', $version->id) }}" class="filtres">
                        @csrf
                        <input name="motif" required minlength="5" placeholder="motif d'annulation (RG-37)">
                        <button class="bouton bouton-court bouton-danger">Annuler la convention approuvée</button>
                    </form>
                @endif
            @endif
        @endforeach
    </div>
@endif
