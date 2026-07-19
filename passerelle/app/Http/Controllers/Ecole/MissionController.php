<?php

namespace App\Http\Controllers\Ecole;

use App\Mail\MissionMail;
use App\Models\JournalAudit;
use App\Models\Mission;
use App\Models\TuteurPedagogique;
use App\Services\ConventionService;
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
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly ConventionService $conventions,
    ) {}

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
            'versionsConvention.actions', 'jalons', 'signalements.emetteur', 'signalements.traitant',
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

    /** UC-10 : génération de la convention (RG-31 complétude, RG-32 version immuable, RG-45 modèle). */
    public function genererConvention(int $id): RedirectResponse
    {
        $mission = $this->missions()->where('statut', 'en_contractualisation')
            ->with('entreprise', 'etudiant.promotion.formation.etablissement', 'tuteurPedagogique', 'tuteurEntreprise')
            ->findOrFail($id);

        if ($mission->versionsConvention()->whereIn('statut', \App\Models\VersionConvention::EN_CIRCULATION)->exists()) {
            return back()->withErrors(['convention' => 'Une version circule déjà : attendez son issue (validation, refus) avant d\'en générer une nouvelle.']);
        }

        $manques = $this->conventions->manques($mission);
        if ($manques !== []) {                                              // RG-31 : liste bloquante
            return back()->withErrors(['convention' => 'Génération bloquée, données manquantes : '.implode(', ', $manques).'.']);
        }

        $version = $this->conventions->generer($mission, auth()->user());

        return back()->with('succes', 'Convention v'.$version->numero.' générée ('
            .($mission->type === 'stage' ? 'convention de stage' : 'dossier d\'alternance')
            .') — transmise à l\'étudiant pour validation.');
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

    /**
     * RG-30 : annulation (avant début) ou interruption (mission active/en évaluation),
     * motivée avec date d'effet — échéancier arrêté, dossier archivé en l'état,
     * parties notifiées, étudiant libéré.
     */
    public function annuler(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate([
            'motif_arret' => ['required', 'string', 'min:5'],
            'date_effet_arret' => ['required', 'date'],
        ]);
        $mission = $this->missions()->whereIn('statut', ['en_montage', 'en_contractualisation', 'contractualisee'])
            ->with('etudiant.compte', 'entreprise.compte')->findOrFail($id);

        $interruption = $mission->statutCalcule() !== 'contractualisee';    // déjà commencée (RG-29)
        $statut = $mission->statut === 'contractualisee' && $interruption ? 'interrompue' : 'annulee';

        DB::transaction(function () use ($mission, $donnees, $statut) {
            $mission->update([...$donnees, 'statut' => $statut]);
            $mission->jalons()->whereNull('fichier_depose')->delete();      // échéancier arrêté
            JournalAudit::tracer('mission_'.$statut, 'mission', $mission->id, auth()->id(), $donnees['motif_arret']);
        });

        Mail::to($mission->etudiant->compte->email)->send(new MissionMail($mission, $statut));
        if ($mission->entreprise->compte->mot_de_passe !== null) {          // partie notifiée si joignable
            Mail::to($mission->entreprise->compte->email)->send(new MissionMail($mission, $statut));
        }

        return back()->with('succes', $statut === 'interrompue'
            ? 'Mission interrompue : dossier archivé en l\'état (rapports rendus conservés).'
            : 'Mission annulée : dossier archivé, l\'étudiant peut de nouveau candidater ou déclarer.');
    }
}
