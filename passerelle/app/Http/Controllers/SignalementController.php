<?php

namespace App\Http\Controllers;

use App\Models\JournalAudit;
use App\Models\Signalement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Traitement des signalements (RG-40) : réservé au tuteur pédagogique de la
 * mission et au responsable de l'école — tout autre compte obtient 404.
 */
class SignalementController extends Controller
{
    private function signalementAutorise(int $id): Signalement
    {
        $signalement = Signalement::with('mission.etudiant.promotion.formation')->findOrFail($id);
        $mission = $signalement->mission;
        $compte = auth()->user();

        $autorise = $compte->id === $mission->tuteur_pedagogique_id
            || ($compte->role === 'responsable'
                && $compte->responsable->etablissement_id
                    === $mission->etudiant->promotion->formation->etablissement_id);
        abort_unless($autorise, 404);

        return $signalement;
    }

    public function prendre(int $id): RedirectResponse
    {
        $signalement = $this->signalementAutorise($id);
        if ($signalement->statut !== 'ouvert') {
            return back()->withErrors(['signalement' => 'Ce signalement est déjà pris en charge ou clos.']);
        }

        $signalement->update(['statut' => 'en_cours', 'traitant_id' => auth()->id()]);
        JournalAudit::tracer('signalement_pris_en_charge', 'signalement', $signalement->id, auth()->id());

        return back()->with('succes', 'Signalement pris en charge.');
    }

    /** RG-40 : la clôture consigne l'issue. */
    public function clore(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['issue' => ['required', 'string', 'min:5']]);
        $signalement = $this->signalementAutorise($id);
        if ($signalement->statut === 'clos') {
            return back()->withErrors(['signalement' => 'Ce signalement est déjà clos.']);
        }

        $signalement->update(['statut' => 'clos', 'issue' => $donnees['issue'], 'traitant_id' => auth()->id()]);
        JournalAudit::tracer('signalement_clos', 'signalement', $signalement->id, auth()->id(), $donnees['issue']);

        return back()->with('succes', 'Signalement clos, issue consignée.');
    }
}
