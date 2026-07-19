<?php

namespace App\Http\Controllers\Ecole;

use App\Mail\MissionMail;
use App\Models\JournalAudit;
use App\Models\Mission;
use App\Models\TuteurPedagogique;
use App\Services\InvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/** Missions des étudiants de l'école : tuteur pédagogique (UC-09), relance, annulation (RG-30). */
class MissionController extends ControleurEcole
{
    public function __construct(private readonly InvitationService $invitations) {}

    private function missions(): Builder
    {
        return Mission::whereHas('etudiant.promotion.formation',
            fn ($q) => $q->where('etablissement_id', $this->etablissement()->id));
    }

    public function index(): View
    {
        $missions = $this->missions()->with([
            'etudiant.promotion.formation', 'entreprise.compte',
            'tuteurPedagogique', 'tuteurEntreprise', 'candidature.offre', 'declaration',
        ])->latest()->get();

        // enseignants rattachés, par formation (E1 UC-09 : blocage explicite si vide)
        $enseignants = TuteurPedagogique::where('etablissement_id', $this->etablissement()->id)
            ->where('actif', true)->with('formations:id')->get()
            ->flatMap(fn ($t) => $t->formations->map(fn ($f) => ['formation_id' => $f->id, 'tuteur' => $t]))
            ->groupBy('formation_id')->map(fn ($g) => $g->pluck('tuteur'));

        return view('ecole.missions.index', ['missions' => $missions, 'enseignants' => $enseignants]);
    }

    /** UC-09 : tuteur pédagogique = enseignant rattaché à la formation de l'étudiant (RG-28). */
    public function designerTuteur(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['tuteur_id' => ['required', 'integer']]);
        $mission = $this->missions()->where('statut', 'en_montage')->with('etudiant.promotion')->findOrFail($id);

        $tuteur = TuteurPedagogique::where('etablissement_id', $this->etablissement()->id)
            ->where('actif', true)->whereKey($donnees['tuteur_id'])
            ->whereHas('formations', fn ($q) => $q->where('formations.id', $mission->etudiant->promotion->formation_id))
            ->first();
        if (! $tuteur) {
            return back()->withErrors(['tuteur' => 'Cet enseignant n\'est pas rattaché à la formation de l\'étudiant (RG-28).']);
        }

        $mission->update(['tuteur_pedagogique_id' => $tuteur->compte_id]);
        JournalAudit::tracer('tuteur_pedagogique_designe', 'mission', $mission->id, auth()->id());
        $mission->contractualiserSiEncadree();

        return back()->with('succes', 'Tuteur pédagogique désigné.');
    }

    /** A3 UC-08 / TV-17 : relance d'une entreprise qui n'a pas activé son compte (RG-02). */
    public function renvoyerInvitation(int $id): RedirectResponse
    {
        $mission = $this->missions()->whereNotNull('declaration_id')->with('entreprise.compte')->findOrFail($id);

        if ($mission->entreprise->compte->mot_de_passe !== null) {
            return back()->withErrors(['invitation' => 'Cette entreprise a déjà activé son compte.']);
        }

        $this->invitations->inviter($mission->entreprise->compte);

        return back()->with('succes', 'Invitation renvoyée (l\'ancien lien est invalidé).');
    }

    /** RG-30 : annulation motivée avant début — dossier archivé, étudiant libéré, parties notifiées. */
    public function annuler(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate([
            'motif_arret' => ['required', 'string', 'min:5'],
            'date_effet_arret' => ['required', 'date'],
        ]);
        $mission = $this->missions()->whereIn('statut', ['en_montage', 'en_contractualisation'])
            ->with('etudiant.compte', 'entreprise.compte')->findOrFail($id);

        DB::transaction(function () use ($mission, $donnees) {
            $mission->update([...$donnees, 'statut' => 'annulee']);
            JournalAudit::tracer('mission_annulee', 'mission', $mission->id, auth()->id(), $donnees['motif_arret']);
        });

        Mail::to($mission->etudiant->compte->email)->send(new MissionMail($mission, 'annulee'));
        if ($mission->entreprise->compte->mot_de_passe !== null) {          // partie notifiée si joignable
            Mail::to($mission->entreprise->compte->email)->send(new MissionMail($mission, 'annulee'));
        }

        return back()->with('succes', 'Mission annulée : dossier archivé, l\'étudiant peut de nouveau candidater ou déclarer.');
    }
}
