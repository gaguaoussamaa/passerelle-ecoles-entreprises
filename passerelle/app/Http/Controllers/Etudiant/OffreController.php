<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\Candidature;
use App\Models\Offre;
use Illuminate\View\View;

class OffreController extends Controller
{
    public function index(): View
    {
        return view('etudiant.offres.index', [
            'offres' => Offre::visiblesPar(auth()->user()->etudiant)
                ->with(['entreprise', 'domaine'])->latest()->get(),
        ]);
    }

    public function detail(int $id): View
    {
        $etudiant = auth()->user()->etudiant;
        $offre = Offre::visiblesPar($etudiant)
            ->with(['entreprise', 'domaine'])->findOrFail($id);             // cloisonnement

        return view('etudiant.offres.detail', [
            'offre' => $offre,
            'etudiant' => $etudiant,
            'candidature' => Candidature::where('etudiant_id', $etudiant->compte_id)
                ->where('offre_id', $offre->id)->first(),                   // RG-22 : une seule
        ]);
    }
}
