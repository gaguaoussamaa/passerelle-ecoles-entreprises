<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Offre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

class OffreController extends Controller
{
    /**
     * Visibilité (RG-19) : offre publiée + diffusion VALIDÉE dans l'école de
     * l'étudiant + affectation à SA promotion. Tout le reste est invisible.
     */
    private function offresVisibles(): Builder
    {
        $etudiant = auth()->user()->etudiant;
        $etabId = $etudiant->promotion->formation->etablissement_id;

        return Offre::where('statut', 'publiee')
            ->whereHas('diffusions', fn ($q) => $q->where('etablissement_id', $etabId)->where('statut', 'validee'))
            ->whereHas('promotions', fn ($q) => $q->where('promotions.id', $etudiant->promotion_id));
    }

    public function index(): View
    {
        return view('etudiant.offres.index', [
            'offres' => $this->offresVisibles()->with(['entreprise', 'domaine'])->latest()->get(),
        ]);
    }

    public function detail(int $id): View
    {
        return view('etudiant.offres.detail', [
            'offre' => $this->offresVisibles()->with(['entreprise', 'domaine'])->findOrFail($id),   // cloisonnement
        ]);
    }
}
