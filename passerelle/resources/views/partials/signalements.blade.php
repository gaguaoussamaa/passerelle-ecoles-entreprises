{{-- Signalements d'une mission ($mission, $peutTraiter) — RG-40 --}}
@foreach ($mission->signalements->sortByDesc('id') as $signalement)
    <div class="message {{ $signalement->statut === 'clos' ? 'message-succes' : 'message-erreur' }}">
        <strong>Signalement {{ \App\Models\Signalement::LIBELLES[$signalement->statut] }}</strong>
        — {{ $signalement->emetteur?->role === 'entreprise' ? 'entreprise' : 'étudiant' }},
        le {{ $signalement->created_at->format('d/m/Y') }} : {{ $signalement->description }}
        @if ($signalement->traitant) <br><small>Traitant : {{ $signalement->traitant->email }}</small> @endif
        @if ($signalement->issue) <br><small>Issue : {{ $signalement->issue }}</small> @endif
        @if ($peutTraiter && $signalement->statut !== 'clos')
            <div class="filtres">
                @if ($signalement->statut === 'ouvert')
                    <form method="POST" action="{{ route('signalements.prendre', $signalement->id) }}">
                        @csrf <button class="bouton bouton-court">Prendre en charge</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('signalements.clore', $signalement->id) }}">
                    @csrf
                    <input name="issue" required minlength="5" placeholder="issue consignée (obligatoire)">
                    <button class="bouton bouton-court">Clore</button>
                </form>
            </div>
        @endif
    </div>
@endforeach
