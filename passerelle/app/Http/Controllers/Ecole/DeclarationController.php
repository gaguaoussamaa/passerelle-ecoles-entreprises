<?php

namespace App\Http\Controllers\Ecole;

use App\Mail\MissionMail;
use App\Models\Compte;
use App\Models\Declaration;
use App\Models\Entreprise;
use App\Models\JournalAudit;
use App\Models\Mission;
use App\Models\Partenariat;
use App\Services\InvitationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/** Recevabilité des déclarations hors plateforme (UC-08 — RG-27, RG-46, RG-47). */
class DeclarationController extends ControleurEcole
{
    public function __construct(private readonly InvitationService $invitations) {}

    private function declarations(): Builder
    {
        return Declaration::whereHas('etudiant.promotion.formation',
            fn ($q) => $q->where('etablissement_id', $this->etablissement()->id));
    }

    public function index(): View
    {
        return view('ecole.declarations.index', [
            'aExaminer' => $this->declarations()->where('statut', 'soumise')
                ->with('etudiant.promotion.formation')->oldest('updated_at')->get(),
            'traitees' => $this->declarations()->whereIn('statut', ['recevable', 'refusee', 'abandonnee'])
                ->with('etudiant', 'mission')->latest('updated_at')->limit(20)->get(),
        ]);
    }

    /**
     * RG-27 : à la validation, entreprise inconnue ⇒ compte invité + partenariat
     * « en attente » (actif à l'activation, RG-47) ; connue ⇒ notifiée.
     * La mission naît « en montage », origine declaration_id (chemin B, RG-26).
     */
    public function valider(int $id): RedirectResponse
    {
        $declaration = $this->declarations()->where('statut', 'soumise')
            ->with('etudiant')->findOrFail($id);

        $compte = Compte::where('email', $declaration->contact_email)->first();
        if ($compte && $compte->role !== 'entreprise') {
            return back()->withErrors(['valider' => 'Le contact indiqué correspond à un compte non-entreprise : demandez à l\'étudiant de corriger sa déclaration.']);
        }

        DB::transaction(function () use ($declaration, &$compte) {
            $nouvelle = $compte === null;
            if ($nouvelle) {                                                // RG-27 : compte « invité »
                $compte = Compte::create(['email' => $declaration->contact_email, 'role' => 'entreprise']);
                Entreprise::create([
                    'compte_id' => $compte->id,
                    'raison_sociale' => $declaration->entreprise_saisie,
                    'siret' => $declaration->siret_saisi,
                ]);
            }

            Partenariat::firstOrCreate(
                ['etablissement_id' => $this->etablissement()->id, 'entreprise_id' => $compte->id],
                ['statut' => $compte->mot_de_passe ? 'actif' : 'en_attente'],   // RG-47
            );

            $declaration->update(['statut' => 'recevable']);
            $mission = Mission::create([
                'etudiant_id' => $declaration->etudiant_id,
                'entreprise_id' => $compte->id,
                'declaration_id' => $declaration->id,                       // origine chemin B
                'type' => $declaration->type_mission,
                'date_debut' => $declaration->date_debut_prevue,
                'date_fin' => $declaration->date_fin_prevue,
            ]);
            JournalAudit::tracer('declaration_recevable', 'declaration', $declaration->id, auth()->id());
            JournalAudit::tracer('mission_creee', 'mission', $mission->id, auth()->id());

            if ($nouvelle) {
                $this->invitations->inviter($compte);                       // l'invitation part automatiquement
            } else {
                Mail::to($compte->email)->send(new MissionMail($mission, 'entreprise_raccordee'));
            }
        });

        return back()->with('succes', 'Déclaration recevable : mission créée en montage, entreprise '
            .($compte->mot_de_passe ? 'notifiée.' : 'invitée à activer son compte.'));
    }

    /** Refus motivé (RG-46) : l'étudiant peut corriger et re-soumettre. */
    public function refuser(Request $request, int $id): RedirectResponse
    {
        $donnees = $request->validate(['motif_refus' => ['required', 'string', 'min:5']]);
        $declaration = $this->declarations()->where('statut', 'soumise')->findOrFail($id);

        $declaration->update(['statut' => 'refusee', 'motif_refus' => $donnees['motif_refus']]);
        JournalAudit::tracer('declaration_refusee', 'declaration', $declaration->id, auth()->id(), $donnees['motif_refus']);

        return back()->with('succes', 'Déclaration refusée : l\'étudiant peut la corriger et la soumettre à nouveau.');
    }
}
