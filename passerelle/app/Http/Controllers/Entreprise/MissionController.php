<?php

namespace App\Http\Controllers\Entreprise;

use App\Http\Controllers\Controller;
use App\Models\JournalAudit;
use App\Models\Mission;
use App\Models\TuteurEntreprise;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Missions de l'entreprise connectée : désignation du tuteur en entreprise (UC-09). */
class MissionController extends Controller
{
    private function miennes(): Builder
    {
        return Mission::where('entreprise_id', auth()->id());
    }

    public function index(): View
    {
        return view('entreprise.missions.index', [
            'missions' => $this->miennes()->with([
                'etudiant.promotion.formation.etablissement',
                'tuteurPedagogique', 'tuteurEntreprise', 'signalements.traitant',
            ])->latest()->get(),
            'tuteurs' => TuteurEntreprise::where('entreprise_id', auth()->id())->orderBy('nom')->get(),
        ]);
    }

    /** RG-40 : l'entreprise peut aussi signaler une difficulté. */
    public function signaler(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['description' => ['required', 'string', 'min:10']]);
        $mission = $this->miennes()->where('statut', 'contractualisee')->findOrFail($id);

        $signalement = \App\Models\Signalement::create([
            'mission_id' => $mission->id, 'emetteur_id' => auth()->id(),
            'description' => $donnees['description'],
        ]);
        JournalAudit::tracer('signalement_ouvert', 'signalement', $signalement->id, auth()->id());

        return back()->with('succes', 'Difficulté signalée : le tuteur pédagogique et le responsable sont alertés.');
    }

    /** UC-09 : le tuteur en entreprise n'a pas de compte (Should) — simple fiche nominative. */
    public function designerTuteur(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate([
            'nom' => ['required', 'string', 'max:80'],
            'prenom' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'],
        ]);
        $mission = $this->miennes()->where('statut', 'en_montage')->findOrFail($id);

        $tuteur = TuteurEntreprise::firstOrCreate(
            ['entreprise_id' => auth()->id(), 'nom' => $donnees['nom'], 'prenom' => $donnees['prenom']],
            ['email' => $donnees['email'] ?? null],
        );
        $mission->update(['tuteur_entreprise_id' => $tuteur->id]);
        JournalAudit::tracer('tuteur_entreprise_designe', 'mission', $mission->id, auth()->id());
        $mission->contractualiserSiEncadree();

        return back()->with('succes', 'Tuteur en entreprise désigné.');
    }
}
