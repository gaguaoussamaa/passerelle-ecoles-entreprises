{{-- Échéancier d'une mission ($mission, $peutDeposer) — états calculés (RG-39) --}}
@if ($mission->jalons->isNotEmpty())
    <table class="tableau">
        <thead><tr><th>Jalon</th><th>Échéance</th><th>État</th><th>Rapport</th></tr></thead>
        <tbody>
        @foreach ($mission->jalons->sortBy('date_echeance') as $jalon)
            <tr>
                <td>{{ $jalon->type === 'mi_parcours' ? 'Point mi-parcours' : 'Rapport mensuel' }}</td>
                <td>{{ $jalon->date_echeance->format('d/m/Y') }}</td>
                <td><span class="badge {{ ['rendu' => 'badge-vert', 'rendu_tardif' => 'badge-orange', 'en_retard' => 'badge-rouge', 'a_venir' => 'badge-gris'][$jalon->etat()] }}">{{ $jalon->libelleEtat() }}</span>
                    @if ($jalon->etat() === 'rendu_tardif')
                        <small>déposé le {{ $jalon->date_depot->format('d/m/Y') }}</small>
                    @endif</td>
                <td>
                    @if ($jalon->fichier_depose)
                        <a href="{{ route('jalons.rapport', $jalon->id) }}">Télécharger</a>
                    @endif
                    @if ($peutDeposer && $mission->statut === 'contractualisee')
                        <form method="POST" action="{{ route('jalons.deposer', $jalon->id) }}" enctype="multipart/form-data" class="filtres">
                            @csrf
                            <input type="file" name="rapport" accept="application/pdf" required>
                            <button class="bouton bouton-court">{{ $jalon->fichier_depose ? 'Remplacer' : 'Déposer' }}</button>
                        </form>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    <p><small>Échéancier non généré : il apparaît à l'approbation complète de la convention.</small></p>
@endif
