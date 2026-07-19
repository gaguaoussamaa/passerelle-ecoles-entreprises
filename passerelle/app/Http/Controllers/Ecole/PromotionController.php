<?php

namespace App\Http\Controllers\Ecole;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PromotionController extends ControleurEcole
{
    public function index(): View
    {
        return view('ecole.promotions.index', [
            'promotions' => $this->promotionsEtablissement()->with('formation')->withCount('etudiants')->orderByDesc('annee_universitaire')->get(),
            'formations' => $this->etablissement()->formations()->where('archivee', false)->orderBy('intitule')->get(),
        ]);
    }

    public function creer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'formation_id' => ['required', 'integer'],
            'libelle' => ['required', 'string', 'max:100'],
            'annee_universitaire' => ['required', 'regex:/^\d{4}-\d{4}$/'],
        ]);

        // cloisonnement : la formation doit appartenir à l'établissement
        $formation = $this->etablissement()->formations()->findOrFail($donnees['formation_id']);
        $formation->promotions()->create($donnees);

        return back()->with('statut', "Promotion « {$donnees['libelle']} » créée.");
    }

    /** RG-11 : l'archivage masque sans perdre. */
    public function archiver(int $id): RedirectResponse
    {
        $promotion = $this->promotionsEtablissement()->findOrFail($id);
        $promotion->update(['archivee' => ! $promotion->archivee]);

        return back()->with('statut', $promotion->archivee ? 'Promotion archivée.' : 'Promotion réactivée.');
    }
}
