<?php

namespace App\Http\Controllers\Tuteur;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use Illuminate\View\View;

/** Suivi côté tuteur pédagogique (UC-14) : jalons, retards (RG-39), signalements. */
class SuiviController extends Controller
{
    public function index(): View
    {
        return view('tuteur.suivi.index', [
            'missions' => Mission::where('tuteur_pedagogique_id', auth()->id())
                ->whereIn('statut', ['contractualisee', 'cloturee', 'interrompue'])
                ->with(['etudiant', 'entreprise', 'jalons' => fn ($q) => $q->orderBy('date_echeance'),
                    'signalements.emetteur', 'signalements.traitant'])
                ->latest()->get(),
        ]);
    }
}
