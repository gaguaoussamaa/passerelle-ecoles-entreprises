<?php

namespace App\Http\Controllers\Tuteur;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\View\View;

/** Espace tuteur pédagogique : conventions de ses missions (validation RG-33, approbation RG-35). */
class ConventionController extends Controller
{
    public function index(): View
    {
        return view('tuteur.conventions.index', [
            'missions' => Mission::where('tuteur_pedagogique_id', auth()->id())
                ->with(['etudiant.promotion.formation', 'entreprise', 'versionsConvention.actions'])
                ->latest()->get(),
        ]);
    }
}
