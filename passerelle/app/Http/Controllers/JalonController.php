<?php

namespace App\Http\Controllers;

use App\Models\Jalon;
use App\Models\JournalAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rapports d'étape sur jalons (UC-14). Le dépôt appartient à l'étudiant de la
 * mission ; la lecture est ouverte à l'étudiant, au tuteur pédagogique et au
 * responsable de l'école (RG-39) — tout autre compte obtient 404.
 */
class JalonController extends Controller
{
    private function jalonAutorise(int $id, bool $depot = false): Jalon
    {
        $jalon = Jalon::with('mission.etudiant.promotion.formation')->findOrFail($id);
        $mission = $jalon->mission;
        $compte = auth()->user();

        $autorise = match (true) {
            $compte->id === $mission->etudiant_id => true,
            $depot => false,                                            // seul l'étudiant dépose
            $compte->id === $mission->tuteur_pedagogique_id => true,
            $compte->role === 'responsable'
                && $compte->responsable->etablissement_id
                    === $mission->etudiant->promotion->formation->etablissement_id => true,
            default => false,
        };
        abort_unless($autorise, 404);

        return $jalon;
    }

    /** Dépôt (ou re-dépôt tardif — l'historique du retard reste lisible via les dates). */
    public function deposer(Request $request, int $id): RedirectResponse
    {
        $request->validate(['rapport' => ['required', 'file', 'mimes:pdf', 'max:4096']]);
        $jalon = $this->jalonAutorise($id, depot: true);
        abort_if($jalon->mission->statut !== 'contractualisee', 404);       // suivi actif seulement

        $chemin = $request->file('rapport')->storeAs('rapports/'.$jalon->mission_id, 'jalon-'.$jalon->id.'.pdf');
        $jalon->update(['fichier_depose' => $chemin, 'date_depot' => now()]);
        JournalAudit::tracer('rapport_depose', 'jalon', $jalon->id, auth()->id());

        return back()->with('succes', 'Rapport déposé.');
    }

    public function rapport(int $id): StreamedResponse
    {
        $jalon = $this->jalonAutorise($id);
        abort_unless($jalon->fichier_depose && Storage::exists($jalon->fichier_depose), 404);

        return Storage::download($jalon->fichier_depose,
            'rapport-mission-'.$jalon->mission_id.'-jalon-'.$jalon->id.'.pdf');
    }
}
