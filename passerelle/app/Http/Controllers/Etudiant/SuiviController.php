<?php

namespace App\Http\Controllers\Etudiant;

use App\Http\Controllers\Controller;
use App\Models\JournalAudit;
use App\Models\Mission;
use App\Models\Signalement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Suivi de mission côté étudiant (UC-14/15) : échéancier, dépôts, signalements. */
class SuiviController extends Controller
{
    public function index(): View
    {
        return view('etudiant.suivi.index', [
            'missions' => Mission::where('etudiant_id', auth()->id())
                ->whereIn('statut', ['contractualisee', 'cloturee', 'interrompue'])
                ->with(['entreprise', 'jalons' => fn ($q) => $q->orderBy('date_echeance'), 'signalements.traitant'])
                ->latest()->get(),
        ]);
    }

    /** RG-40 : signalement d'une difficulté — alerte pour le tuteur et le responsable. */
    public function signaler(Request $request, int $missionId): RedirectResponse
    {
        $donnees = $request->validate(['description' => ['required', 'string', 'min:10']]);
        $mission = Mission::where('etudiant_id', auth()->id())
            ->where('statut', 'contractualisee')->findOrFail($missionId);

        $signalement = Signalement::create([
            'mission_id' => $mission->id, 'emetteur_id' => auth()->id(),
            'description' => $donnees['description'],
        ]);
        JournalAudit::tracer('signalement_ouvert', 'signalement', $signalement->id, auth()->id());

        return back()->with('succes', 'Difficulté signalée : votre tuteur et votre responsable sont alertés.');
    }
}
